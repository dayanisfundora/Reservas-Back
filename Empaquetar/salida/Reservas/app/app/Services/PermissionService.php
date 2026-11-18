<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operador;
use App\Models\Modulo;
use App\Models\Aplicacion;
use App\Models\Opcion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /**
     * Get all permissions for an operator in a specific module.
     *
     * @param int $id_oper Operator ID
     * @param int $id_modulo Module ID (default: 35 for Presupuesto)
     * @return array
     */
    public function get_operator_permissions(int $id_oper, int $id_modulo = 35): array
    {
        try {
            // Query Acceso_Opc table to get opciones for this operator
            $accesos = DB::connection('ihsecurity')
                ->table('Acceso_Opc')
                ->where('id_oper', $id_oper)
                ->get();

            if ($accesos->isEmpty()) {
                Log::warning("No access records found for operator {$id_oper}");
                return [];
            }

            // Get option IDs this operator has access to
            $option_ids = $accesos->pluck('id_opc')->toArray();
            Log::info("Operator {$id_oper} has access to " . count($option_ids) . " options");

            // Get parent IDs of options the user has access to, using chunking to avoid SQL Server parameter limit
            $parent_ids = [];
            $chunk_size = 2000; // Menor a 2100 por seguridad
            foreach (array_chunk($option_ids, $chunk_size) as $chunk) {
                $ids = DB::connection('ihsecurity')
                    ->table('Opciones')
                    ->whereIn('id_opc', $chunk)
                    ->whereNotNull('id_parent')
                    ->pluck('id_parent')
                    ->toArray();
                $parent_ids = array_merge($parent_ids, $ids);
            }
            $parent_ids = array_unique($parent_ids);

            // Merge option IDs with their parent IDs to ensure parents are loaded
            $all_option_ids = array_unique(array_merge($option_ids, $parent_ids));

            // Get module with applications and options filtered by operator permissions
            // Load opciones without children first to avoid SQL parameter limit
            $modulo = Modulo::with(['aplicaciones.opciones' => function ($query) use ($all_option_ids, $chunk_size) {
                $query->where(function ($q) use ($all_option_ids, $chunk_size) {
                    foreach (array_chunk($all_option_ids, $chunk_size) as $chunk) {
                        $q->orWhereIn('id_opc', $chunk);
                    }
                });
            }])
            ->where('id_modulo', $id_modulo)
            ->first();

            if (!$modulo) {
                Log::error("Module {$id_modulo} not found in database");
                return [];
            }

            if ($modulo->aplicaciones->isEmpty()) {
                Log::warning("Module {$id_modulo} has no applications");
                return [];
            }

            // Now load children manually with chunking to avoid SQL parameter limit
            foreach ($modulo->aplicaciones as $aplicacion) {
                foreach ($aplicacion->opciones as $opcion) {
                    // Load children with chunking
                    $children_collection = collect();
                    foreach (array_chunk($option_ids, $chunk_size) as $chunk) {
                        $children_batch = Opcion::where('id_parent', $opcion->id_opc)
                            ->whereIn('id_opc', $chunk)
                            ->get();
                        $children_collection = $children_collection->merge($children_batch);
                    }
                    // Manually set the children relationship
                    $opcion->setRelation('children', $children_collection);
                }
            }

            Log::info("Module {$id_modulo} has " . $modulo->aplicaciones->count() . " applications");

            return $this->format_permissions($modulo, $option_ids);
        } catch (\Exception $e) {
            Log::error('Error getting operator permissions: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Format permissions into a structured array.
     *
     * @param Modulo $modulo
     * @param array $option_ids IDs of options the operator has access to
     * @return array
     */
    private function format_permissions(Modulo $modulo, array $option_ids): array
    {
        $formatted = [];

        foreach ($modulo->aplicaciones as $aplicacion) {
            foreach ($aplicacion->opciones as $opcion) {
                // Check if this is an action child by examining the option model
                if ($this->is_action_option($opcion)) {
                    // This is an action child (Ver X, Crear X, etc.) - skip it
                    // It will be processed as part of its parent
                    continue;
                }
                
                // Check if this option has children (action sub-options)
                $has_children = $opcion->children && $opcion->children->isNotEmpty();
                
                if ($has_children) {
                    // This is a parent option with actions - add parent permission first
                    $parent_key = $this->create_permission_key($opcion->desc_opc);
                    $formatted[] = $parent_key . '.ver';
                    
                    // Process each child action (Ver, Crear, Editar, Eliminar)
                    foreach ($opcion->children as $child) {
                        $permission = $this->create_permission_from_action($opcion->desc_opc, $child->desc_opc);
                        if ($permission) {
                            $formatted[] = $permission;
                        }
                    }
                } else {
                    // This is a standalone option without children - add default CRUD permissions
                    $permission_key = $this->create_permission_key($opcion->desc_opc);
                    
                    $formatted[] = $permission_key . '.ver';
                    $formatted[] = $permission_key . '.crear';
                    $formatted[] = $permission_key . '.editar';
                    $formatted[] = $permission_key . '.eliminar';
                }
            }
        }

        return array_values(array_unique($formatted));
    }

    /**
     * Check if an option is an action (child option that represents an action).
     *
     * @param Opcion $opcion Option model
     * @return bool
     */
    private function is_action_option(Opcion $opcion): bool
    {
        // Check if desc_opc_api contains "Action:" which indicates it's an action child
        if (!empty($opcion->desc_opc_api) && stripos($opcion->desc_opc_api, 'Action:') !== false) {
            return true;
        }
        
        // Fallback: Check if description starts with common action keywords
        $action_keywords = ['Ver ', 'Crear ', 'Editar ', 'Eliminar ', 'Exportar ', 'Cancelar ', 'Modificación ', 'Índice '];
        
        foreach ($action_keywords as $keyword) {
            if (stripos($opcion->desc_opc, $keyword) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Create permission string from parent option and action description.
     * Converts "Ver Entidad" -> "entidad.ver"
     *
     * @param string $parent_desc Parent option description (e.g., "Entidad")
     * @param string $action_desc Action description (e.g., "Ver Entidad", "Crear Entidad")
     * @return string|null
     */
    private function create_permission_from_action(string $parent_desc, string $action_desc): ?string
    {
        // Extract action word from description
        $action_map = [
            'Ver' => 'ver',
            'Crear' => 'crear',
            'Editar' => 'editar',
            'Eliminar' => 'eliminar',
            'Exportar' => 'exportar',
            'Cancelar' => 'cancelar',
            'Modificación' => 'modificar',
            'Índice' => 'indice',
        ];

        foreach ($action_map as $spanish => $english) {
            if (stripos($action_desc, $spanish) === 0) {
                $permission_key = $this->create_permission_key($parent_desc);
                
                // Special case: "Crear / Editar Presupuesto" should map to "crear_editar_presupuesto"
                if (stripos($action_desc, 'Crear / Editar') === 0) {
                    return $permission_key . '.crear_editar_presupuesto';
                }
                
                // Special case: other specific actions with full context
                if (stripos($action_desc, 'Modificación de Presupuesto') === 0) {
                    return $permission_key . '.modificacion_presupuesto';
                }
                if (stripos($action_desc, 'Exportar Presupuesto') === 0) {
                    return $permission_key . '.exportar_presupuesto';
                }
                if (stripos($action_desc, 'Índice de Presupuesto') === 0) {
                    return $permission_key . '.indice_presupuesto';
                }
                
                return $permission_key . '.' . $english;
            }
        }

        // If no action found, return null
        Log::warning("Could not extract action from: {$action_desc}");
        return null;
    }

    /**
     * Create a permission key from option description.
     *
     * @param string $desc_opc
     * @return string
     */
    private function create_permission_key(string $desc_opc): string
    {
        // Map specific option names to permission keys
        $mappings = [
            'Inicio' => 'inicio',
            'Nomencladores' => 'nomencladores',
            'Gestiones Nacionales' => 'gestiones_nacionales',
            'Gestiones Exterior' => 'gestiones_exterior',
            'Trazas' => 'trazas',
        ];

        // Check if there's a direct mapping
        if (isset($mappings[$desc_opc])) {
            return $mappings[$desc_opc];
        }

        // Convert description to snake_case permission key
        $key = strtolower($desc_opc);
        $key = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        
        // Pluralize for consistency
        if (!str_ends_with($key, 's')) {
            $key .= 's';
        }
        
        return $key;
    }

    /**
     * Get detailed permissions structure for an operator.
     *
     * @param int $id_oper Operator ID
     * @param int $id_modulo Module ID (default: 35 for Presupuesto)
     * @return array
     */
    public function get_operator_permissions_detailed(int $id_oper, int $id_modulo = 35): array
    {
        // Get module with applications and options
        $modulo = Modulo::with(['aplicaciones.opciones.children'])
            ->where('id_modulo', $id_modulo)
            ->first();

        if (!$modulo) {
            return [];
        }

        $result = [];

        foreach ($modulo->aplicaciones as $aplicacion) {
            foreach ($aplicacion->opciones as $opcion) {
                $result[] = [
                    'id_opc' => $opcion->id_opc,
                    'desc_opc' => $opcion->desc_opc,
                    'id_app' => $aplicacion->id_app,
                    'desc_app' => $aplicacion->desc_app,
                    'id_parent' => $opcion->id_parent,
                    'permissions' => [
                        'ver' => true,
                        'crear' => true,
                        'editar' => true,
                        'eliminar' => true,
                        'imprimir' => true,
                    ],
                ];
            }
        }

        return $result;
    }

    /**
     * Check if operator has specific permission.
     *
     * @param int $id_oper Operator ID
     * @param string $permission Permission string (e.g., 'grupos.ver')
     * @return bool
     */
    public function has_permission(int $id_oper, string $permission): bool
    {
        $permissions = $this->get_operator_permissions($id_oper);
        return in_array($permission, $permissions);
    }
}
