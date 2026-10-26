# Agent Rules
- Cada función nueva debe incluir un bloque de comentario PHPDoc encima en inglés.
- Todas las variables y funciones deben escribirse en snake_case.
- No uses camelCase.
- Todas las variables y funciones escritas en idioma inglés.

# Convención para mensajes de commit
- Los commits generados deben tener una estructura clara:
	- Título breve y descriptivo en presente (máx. 72 caracteres).
	- Línea en blanco.
	- Descripción detallada de cada funcionalidad nueva, cambio o fix.
	- Si hay varias funcionalidades nuevas, lista cada una con un punto o guion.
	- Si aplica, referencia archivos, clases o métodos modificados.
	- Ejemplo:
		```
		Add SupplyInvoiceAgent with sum method

		- Implement SupplyInvoiceAgent class in app/Agents/
		- Add add() method to sum two numbers
		- Include PHPDoc and unit test for add()
		```

Esto asegura trazabilidad y claridad en el historial de cambios.

## Recomendaciones adicionales

- Ubica los agentes en la carpeta `app/Agents/` para mantener la organización.
- Los archivos de agentes deben nombrarse en snake_case y las clases en PascalCase, siempre en inglés.
- Usa `declare(strict_types=1);` al inicio de cada archivo PHP para tipado estricto.
- Prefiere la inyección de dependencias en el constructor para mayor testabilidad.
- Cada agente debe tener pruebas unitarias en `tests/Unit/Agents/`.
- Documenta ejemplos de uso en los comentarios PHPDoc de cada clase o método relevante.
- Maneja errores lanzando excepciones personalizadas cuando sea necesario.
- Usa constantes para valores fijos en vez de literales mágicos.
- Define la visibilidad adecuada para métodos y propiedades (privado/protegido/público).
- Si el agente interactúa con la base de datos, sigue las convenciones de nombres para migraciones, seeders y factories.

---

## Convenciones para generación de CRUD, modelos y migraciones

- Los nombres de tablas deben ir en plural y snake_case (ejemplo: `supply_invoices`).
- Los modelos van en singular y PascalCase (ejemplo: `SupplyInvoice`).
- Todas las tablas deben tener los campos `id`, `created_at`, `updated_at` por defecto.
- Si es necesario, agrega `deleted_at` para soft deletes.
- Si hay relación con usuario o empresa, usa siempre `user_id` o `company_id` como foreign key.
- Los controladores deben generarse como resource controllers y ubicarse en `app/Http/Controllers/`.
- Las migraciones deben crearse con el prefijo `create_` seguido del nombre de la tabla.
- Usa Form Requests para validación y ubícalos en `app/Http/Requests/`.
- Por defecto, usa IDs autoincrementales (no UUIDs, salvo que se indique lo contrario).
- Los campos booleanos deben ser `is_active`, `is_deleted`, etc., en snake_case.
- Si necesitas relaciones, especifica el tipo (hasMany, belongsTo, etc.) y los nombres de los métodos en snake_case.

### Ejemplo de migración
```php
Schema::create('supply_invoices', function (Blueprint $table) {
	$table->id();
	$table->string('invoice_number');
	$table->unsignedBigInteger('user_id');
	$table->timestamps();
	$table->softDeletes();
});
```

### Ejemplo de modelo
```php
class SupplyInvoice extends Model
{
	protected $fillable = [
		'invoice_number',
		'user_id',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
```

---
Si alguna de estas convenciones cambia, actualiza esta sección para mantener la coherencia en el desarrollo y en las respuestas automáticas.

---

## Convenciones para tests de funciones

- Por cada método público de un modelo, controlador o agente, debe existir al menos una prueba unitaria o de integración.
- Los tests deben ubicarse en `tests/Unit/` para lógica de negocio y en `tests/Feature/` para endpoints o flujos completos.
- El nombre del método de test debe reflejar el método probado y el caso de uso (ejemplo: `test_store_creates_invoice_correctly`).
- Usa PHPUnit y las utilidades de Laravel (factories, assertions, mocks).

### Ejemplo de test unitario para un método de agente
```php
namespace Tests\Unit\Agents;

use App\Agents\SupplyInvoiceAgent;
use PHPUnit\Framework\TestCase;

class SupplyInvoiceAgentTest extends TestCase
{
	/** @test */
	public function test_process_returns_expected_result()
	{
		$agent = new SupplyInvoiceAgent();
		$data = [/* datos de prueba */];
		$result = $agent->process($data);
		$this->assertIsArray($result);
		// Agrega más asserts según la lógica
	}
}
```

---
Recuerda mantener los tests actualizados y cubrir los casos relevantes para cada función pública.

### Buenas prácticas para funciones testeables

- Cada función pública debe ser atómica y tener una única responsabilidad.
- Evita efectos secundarios inesperados (no modificar variables globales ni estados ocultos).
- Usa dependencias inyectadas para facilitar el uso de mocks en los tests.
- Retorna valores claros y predecibles.
- Documenta y prueba los casos en que la función lanza excepciones.
- Los datos de entrada y salida deben ser fácilmente mockeables (arrays, DTOs, etc.).

#### Ejemplo de función atómica y su test
```php
// SupplyInvoiceAgent.php
class SupplyInvoiceAgent
{
	/**
	 * Suma dos números.
	 *
	 * @param int $a
	 * @param int $b
	 * @return int
	 */
	public function add(int $a, int $b): int
	{
		return $a + $b;
	}
}
```

```php
// SupplyInvoiceAgentTest.php
public function test_add_returns_sum()
{
	$agent = new SupplyInvoiceAgent();
	$this->assertEquals(5, $agent->add(2, 3));
}
```