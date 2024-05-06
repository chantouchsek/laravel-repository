<?php
namespace Prettus\Repository\Criteria;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class RequestCriteria
 * @package Prettus\Repository\Criteria
 * @author Anderson Andrade <contato@andersonandra.de>
 */
class RequestCriteria implements CriteriaInterface
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }


    /**
     * Apply criteria in query repository
     *
     * @param         Builder|Model     $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     * @throws Exception
     */
    public function apply($model, RepositoryInterface $repository): mixed
    {
        $fieldsSearchable = $repository->getFieldsSearchable();
        $search = $this->request->get(config('repository.criteria.params.search', 'search'));
        $searchFields = $this->request->get(config('repository.criteria.params.searchFields', 'searchFields'));
        $filter = $this->request->get(config('repository.criteria.params.filter', 'filter'));
        $orderBy = $this->request->get(config('repository.criteria.params.orderBy', 'orderBy'));
        $sortedBy = $this->request->get(config('repository.criteria.params.sortedBy', 'sortedBy'), 'asc');
        $with = $this->request->get(config('repository.criteria.params.with', 'with'));
        $withCount = $this->request->get(config('repository.criteria.params.withCount', 'withCount'));
        $searchJoin = $this->request->get(config('repository.criteria.params.searchJoin', 'searchJoin'));
        $sortedBy = ! empty($sortedBy) ? $sortedBy : 'asc';

        if ($search && is_array($fieldsSearchable) && count($fieldsSearchable)) {
            $searchFields = is_array($searchFields) || is_null($searchFields) ? $searchFields : explode(';', $searchFields);
            $searchData = $this->parserSearchData($search);
            $fields = $this->parserFieldsSearch($fieldsSearchable, $searchFields, array_keys($searchData));
            $search = $this->parserSearchValue($search);
            $modelForceAndWhere = strtolower($searchJoin) === 'and';

            $model = $model->where(function (Builder $query) use ($fields, $search, $searchData, $modelForceAndWhere) {
                $isFirstField = true;
                foreach ($fields as $field => $condition) {

                    if (is_numeric($field)) {
                        $field = $condition;
                        $condition = '=';
                    }

                    $value = null;

                    $condition = trim(strtolower($condition));

                    if (isset($searchData[$field])) {
                        $value = ($condition == 'like' || $condition == 'ilike') ? "%$searchData[$field]%" : $searchData[$field];
                    } else {
                        if (! is_null($search) && ! in_array($condition, ['in', 'between'])) {
                            $value = ($condition == 'like' || $condition == 'ilike') ? "%$search%" : $search;
                        }
                    }

                    $relation = null;
                    if (stripos($field, '.')) {
                        $explode = explode('.', $field);
                        $field = array_pop($explode);
                        $relation = implode('.', $explode);
                    }
                    if ($condition === 'in') {
                        $value = explode(',', $value);
                        if (trim($value[0]) === '' || $field == $value[0]) {
                            $value = null;
                        }
                    }
                    if ($condition === 'between') {
                        $value = explode(',', $value);
                        if (count($value) < 2) {
                            $value = null;
                        }
                    }
                    $modelTableName = $query->getModel()->getTable();
                    if ($isFirstField || $modelForceAndWhere) {
                        if (is_null($value)) {
                            continue;
                        }

                        $isNotNull = $condition === '!null';
                        if (! is_null($relation)) {
                            $query->whereHas($relation, function (Builder $query) use ($field, $condition, $value, $isNotNull) {
                                if ($condition === 'in') {
                                    $query->whereIn($field, $value);
                                } elseif ($condition === 'between') {
                                    $query->whereBetween($field, $value);
                                } elseif ($condition === 'null') {
                                    $query->whereNull($field);
                                } elseif ($isNotNull) {
                                    $query->whereNotNull($field);
                                } else {
                                    $query->where($field, $condition, $value);
                                }
                            });
                        } else {
                            if ($condition === 'in') {
                                $query->whereIn($modelTableName.'.'.$field, $value);
                            } elseif ($condition === 'between') {
                                $query->whereBetween($modelTableName.'.'.$field, $value);
                            } elseif ($condition === 'null') {
                                $query->whereNull($field);
                            } elseif ($isNotNull && $value !== 'and_or') {
                                $query->whereNotNull($field);
                            } elseif ($isNotNull && $value === 'and_or') {
                                $fields = str_contains($field, ',') ? explode(',', $field) : [$field];
                                $query->where(function (Builder $b) use ($fields) {
                                    foreach ($fields as $field) {
                                        $b->orWhereNotNull($field);
                                    }
                                });
                            } else {
                                $query->where($modelTableName.'.'.$field, $condition, $value);
                            }
                        }
                        $isFirstField = false;
                    } else {
                        if (! is_null($value)) {
                            if (! is_null($relation)) {
                                $query->orWhereHas($relation, function (Builder $query) use ($field, $condition, $value) {
                                    if ($condition === 'in') {
                                        $query->whereIn($field, $value);
                                    } elseif ($condition === 'between') {
                                        $query->whereBetween($field, $value);
                                    } else {
                                        $query->where($field, $condition, $value);
                                    }
                                });
                            } else {
                                if ($condition === 'in') {
                                    $query->orWhereIn($modelTableName.'.'.$field, $value);
                                } elseif ($condition === 'between') {
                                    $query->whereBetween($modelTableName.'.'.$field, $value);
                                } else {
                                    $query->orWhere($modelTableName.'.'.$field, $condition, $value);
                                }
                            }
                        }
                    }
                }
            });
        }

        if (! empty($orderBy)) {
            $orderBySplit = explode(';', $orderBy);
            if (count($orderBySplit) > 1) {
                $sortedBySplit = explode(';', $sortedBy);
                foreach ($orderBySplit as $orderBySplitItemKey => $orderBySplitItem) {
                    $sortedBy = $sortedBySplit[$orderBySplitItemKey] ?? $sortedBySplit[0];
                    $model = $this->parserFieldsOrderBy($model, $orderBySplitItem, $sortedBy);
                }
            } else {
                $model = $this->parserFieldsOrderBy($model, $orderBySplit[0], $sortedBy);
            }
        }

        if (! empty($filter)) {
            if (is_string($filter)) {
                $filter = explode(';', $filter);
            }

            $model = $model->select($filter);
        }

        if ($with) {
            $with = explode(';', $with);
            $model = $model->with($with);
        }

        if ($withCount) {
            $withCount = explode(';', $withCount);
            $model = $model->withCount($withCount);
        }

        return $model;
    }

    /**
     * @param $model
     * @param $orderBy
     * @param $sortedBy
     * @return mixed
     */
    protected function parserFieldsOrderBy($model, $orderBy, $sortedBy): mixed
    {
        $split = explode('|', $orderBy);
        if(count($split) > 1) {
            /*
             * ex.
             * products|description -> join products on current_table.product_id = products.id order by description
             *
             * products:custom_id|products.description -> join products on current_table.custom_id = products.id order
             * by products.description (in case both tables have same column name)
             */
            $table = $model->getModel()->getTable();
            $sortTable = $split[0];
            $sortColumn = $split[1];

            $split = explode(':', $sortTable);
            $localKey = '.id';
            if (count($split) > 1) {
                $sortTable = $split[0];

                $commaExp = explode(',', $split[1]);
                $keyName = $table.'.'.$split[1];
                if (count($commaExp) > 1) {
                    $keyName = $table.'.'.$commaExp[0];
                    $localKey = '.'.$commaExp[1];
                }
            } else {
                /*
                 * If you do not define which column to use as a joining column on current table, it will
                 * use a singular of a join table appended with _id
                 *
                 * ex.
                 * products -> product_id
                 */
                $prefix = Str::singular($sortTable);
                $keyName = $table.'.'.$prefix.'_id';
            }

            $model = $model
                ->leftJoin($sortTable, $keyName, '=', $sortTable.$localKey)
                ->orderBy($sortColumn, $sortedBy)
                ->addSelect($table.'.*');
        } else {
            $model = $model->orderBy($orderBy, $sortedBy);
        }
        return $model;
    }

    /**
     * @param $search
     *
     * @return array
     */
    protected function parserSearchData($search): array
    {
        $searchData = [];

        if (stripos($search, ':')) {
            $fields = explode(';', $search);

            foreach ($fields as $row) {
                try {
                    list($field, $value) = explode(':', $row);
                    $searchData[$field] = $value;
                } catch (Exception $e) {
                    //Surround offset error
                }
            }
        }

        return $searchData;
    }

    /**
     * @param string $search
     *
     * @return string|null
     */
    protected function parserSearchValue(string $search): ?string
    {

        if (stripos($search, ';') || stripos($search, ':')) {
            $values = explode(';', $search);
            foreach ($values as $value) {
                $s = explode(':', $value);
                if (count($s) == 1) {
                    return $s[0];
                }
            }

            return null;
        }

        return $search;
    }

    protected function parserFieldsSearch(array $fields = [], array $searchFields = null, array $dataKeys = null): array
    {
        if (!is_null($searchFields) && count($searchFields)) {
            $acceptedConditions = config('repository.criteria.acceptedConditions', [
                '=',
                'like'
            ]);
            $originalFields = $fields;
            $fields = [];

            foreach ($searchFields as $index => $field) {
                $field_parts = explode(':', $field);
                $temporaryIndex = array_search($field_parts[0], $originalFields);

                if (count($field_parts) == 2) {
                    if (in_array($field_parts[1], $acceptedConditions)) {
                        unset($originalFields[$temporaryIndex]);
                        $field = $field_parts[0];
                        $condition = $field_parts[1];
                        $originalFields[$field] = $condition;
                        $searchFields[$index] = $field;
                    }
                }
            }

            if (!is_null($dataKeys) && count($dataKeys)) {
                $searchFields = array_unique(array_merge($dataKeys, $searchFields));
            }

            foreach ($originalFields as $field => $condition) {
                if (is_numeric($field)) {
                    $field = $condition;
                    $condition = "=";
                }
                if (in_array($field, $searchFields)) {
                    $fields[$field] = $condition;
                }
            }

            if (count($fields) == 0) {
                throw new Exception(trans('repository::criteria.fields_not_accepted', ['field' => implode(',', $searchFields)]));
            }
        }

        return $fields;
    }
}
