<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

/** Les tableaux de widget paginent en « simple » (Précédent / Suivant) ; on veut le compteur et les numéros de page. */
trait PaginationComplete
{
    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $parPage = $this->getTableRecordsPerPage();
        $total = $query->toBase()->getCountForPagination();

        return $query->paginate(
            perPage: $parPage === 'all' ? $total : $parPage,
            columns: ['*'],
            pageName: $this->getTablePaginationPageName(),
            total: $total,
        );
    }
}
