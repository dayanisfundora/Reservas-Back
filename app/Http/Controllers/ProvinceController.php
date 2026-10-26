<?php

namespace App\Http\Controllers;

use App\Http\Requests\OriginRequest;
use App\Http\Requests\ProvinceRequest;
use App\Models\Origin;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Traits\HasBasicCrudOperations;

class ProvinceController extends Controller
{
    use HasBasicCrudOperations;

    /**
     * Constructor to initialize properties for HasBasicCrudOperations trait
     */
    public function __construct()
    {
        $this->modelClass = Province::class;
        $this->uniqueFields = ['code', 'description', 'abbr'];
        $this->allowedSorts = ['code', 'description', 'abbr', 'state', 'id', 'created_at', 'updated_at'];
        $this->searchFields = ['code', 'description', 'abbr'];
        $this->pdfViewName = 'pdf.province';
    }

    /**
     * Custom filter logic for Province index
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filterQuery($query, Request $request)
    {
        if ($request->filled('state')) {
            $stateValue = $request->input('state');
            if ($stateValue === '1' || $stateValue === 1 || $stateValue === true || $stateValue === 'true') {
                $query->where('state', true);
            } elseif ($stateValue === '0' || $stateValue === 0 || $stateValue === false || $stateValue === 'false') {
                $query->where('state', false);
            }
        }
        return $query;
    }

    /**
     * Store a newly created Province with ProvinceRequest validation
     * @param \App\Http\Requests\ProvinceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ProvinceRequest $request)
    {
        return $this->handleStore($request->validated());
    }

    /**
     * Update an existing Province with ProvinceRequest validation
     * @param \App\Http\Requests\ProvinceRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProvinceRequest $request, string $id)
    {
        return $this->handleUpdate($id, $request->validated());
    }
}
