<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RoutesExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        //
        return collect(Route::getRoutes())->map(function ($route) {
            return [
                'Method' => implode('|', $route->methods()),
                // 'URI' => $route->uri(),
                'Name' => $route->getName(),
                'Action' => $route->getActionName(),
                // 'Middleware' => implode(', ', $route->middleware()),
            ];
        });


    }
     public function headings(): array
    {
        return ['Methode','Name', 'Action'];
    }
}
