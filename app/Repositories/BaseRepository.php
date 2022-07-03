<?php

namespace App\Repositories;

use Closure;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseRepository implements RepositoryInterface
{
    /** @var Model */
    protected $model;

    /** @var Closure */
    protected $scopeQuery;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->makeModel();
    }

    /**
     * @return Model
     * @throws Exception()
     */
    public function makeModel(): Model
    {
        $model = app($this->model());

        if (!$model instanceof Model) {
            throw new Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    abstract public function model(): string;

    /**
     * Trigger static method calls to the model
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return call_user_func_array(array(new static(), $method), $arguments);
    }

    /**
     * Returns the current Model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get data array for populate field select
     *
     * @param string      $column
     * @param string|null $key
     *
     * @return Collection
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        return $this->model->pluck($column, $key);
    }

    /**
     * SyncWithoutDetaching
     *
     * @param int|string             $id
     * @param string                 $relation
     * @param Collection|Model|array $attributes
     *
     * @return array
     * @throws Exception
     */
    public function syncWithoutDetaching($id, string $relation, $attributes): array
    {
        return $this->sync($id, $relation, $attributes, false);
    }

    /**
     * Sync relations
     *
     * @param int|string             $id
     * @param string                 $relation
     * @param Collection|Model|array $attributes
     * @param bool                   $detaching
     *
     * @return array
     * @throws Exception
     */
    public function sync($id, string $relation, $attributes, bool $detaching = true): array
    {
        return $this->find($id)->{$relation}()->sync($attributes, $detaching);
    }

    /**
     * Find data by id
     *
     * @param int|string $id
     * @param array      $columns
     * @param string     $trashed
     *
     * @return mixed
     * @throws Exception
     */
    public function find($id, array $columns = array('*'), string $trashed = '')
    {
        try {
            $this->applyScope();

            switch (strtolower($trashed)) {
                case 'withtrashed':
                    $model = $this->model->withTrashed()->findOrFail($id, $columns);
                    break;
                case 'onlytrashed':
                    $model = $this->model->onlyTrashed()->findOrFail($id, $columns);
                    break;
                case '':
                default:
                    $model = $this->model->findOrFail($id, $columns);
            }

            $this->resetModel();
            $this->resetScope();

            return $model;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope(): RepositoryInterface
    {
        if (isset($this->scopeQuery) && is_callable($this->scopeQuery)) {
            $callback = $this->scopeQuery;
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function resetModel()
    {
        $this->makeModel();
    }

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope(): RepositoryInterface
    {
        $this->scopeQuery = null;

        return $this;
    }

    /**
     * Get all data of repository
     *
     * @param array  $columns
     * @param string $trashed
     *
     * @return mixed
     * @throws Exception
     */
    public function all(array $columns = array('*'), string $trashed = '')
    {
        $this->applyScope();

        switch (strtolower($trashed)) {
            case 'withtrashed':
                $this->model = $this->model->withTrashed();
                break;
            case 'onlytrashed':
                $this->model = $this->model->onlyTrashed();
                break;
            case '':
            default:
        }

        if ($this->model instanceof Builder) {
            $result = $this->model->get();
        } else {
            $result = $this->model->all();
        }

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * Find data by field and value
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findByField(string $field, $value, array $columns = array('*'))
    {
        $this->applyScope();

        $model = $this->model->where($field, '=', $value)->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $model;
    }

    /**
     * Find data by multiple fields
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWhere(array $where, array $columns = array('*'))
    {
        $this->applyScope();
        $this->applyConditions($where);

        $results = $this->model->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * Applies the given where conditions to the model.
     *
     * @param array $where
     *
     * @return void
     * @throws Exception
     */
    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                // Replace one or more spaces with one space
                $condition = preg_replace('/\s\s+/', ' ', trim($condition));

                // Split to get operator, syntax: "DATE >", "DATE =", "DAY <"
                $operator = explode(' ', $condition);
                if (count($operator) > 1) {
                    $condition = $operator[0];
                    $operator = $operator[1];
                } else {
                    $operator = null;
                }

                switch (strtoupper($condition)) {
                    case 'IN':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereIn($field, $val);
                        break;
                    case 'NOTIN':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereNotIn($field, $val);
                        break;
                    case 'DATE':
                        if (!$operator) {
                            $operator = '=';
                        }
                        $this->model = $this->model->whereDate($field, $operator, $val);
                        break;
                    case 'DAY':
                        if (!$operator) {
                            $operator = '=';
                        }
                        $this->model = $this->model->whereDay($field, $operator, $val);
                        break;
                    case 'MONTH':
                        if (!$operator) {
                            $operator = '=';
                        }
                        $this->model = $this->model->whereMonth($field, $operator, $val);
                        break;
                    case 'YEAR':
                        if (!$operator) {
                            $operator = '=';
                        }
                        $this->model = $this->model->whereYear($field, $operator, $val);
                        break;
                    case 'EXISTS':
                        if (!($val instanceof Closure)) {
                            throw new Exception("Input $val must be closure function");
                        }
                        $this->model = $this->model->whereExists($val);
                        break;
                    case 'HAS':
                        if (!($val instanceof Closure)) {
                            throw new Exception("Input $val must be closure function");
                        }
                        $this->model = $this->model->whereHas($field, $val);
                        break;
                    case 'HASMORPH':
                        if (!($val instanceof Closure)) {
                            throw new Exception("Input $val must be closure function");
                        }
                        $this->model = $this->model->whereHasMorph($field, $val);
                        break;
                    case 'DOESNTHAVE':
                        if (!($val instanceof Closure)) {
                            throw new Exception("Input $val must be closure function");
                        }
                        $this->model = $this->model->whereDoesntHave($field, $val);
                        break;
                    case 'DOESNTHAVEMORPH':
                        if (!($val instanceof Closure)) {
                            throw new Exception("Input $val must be closure function");
                        }
                        $this->model = $this->model->whereDoesntHaveMorph($field, $val);
                        break;
                    case 'BETWEEN':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereBetween($field, $val);
                        break;
                    case 'BETWEENCOLUMNS':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereBetweenColumns($field, $val);
                        break;
                    case 'NOTBETWEEN':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereNotBetween($field, $val);
                        break;
                    case 'NOTBETWEENCOLUMNS':
                        if (!is_array($val)) {
                            throw new Exception("Input $val mus be an array");
                        }
                        $this->model = $this->model->whereNotBetweenColumns($field, $val);
                        break;
                    case 'RAW':
                        $this->model = $this->model->whereRaw($val);
                        break;
                    default:
                        $this->model = $this->model->where($field, $condition, $val);
                }
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    /**
     * Load relation with closure
     *
     * @param string  $relation
     * @param closure $closure
     *
     * @return $this
     */
    public function whereHas(string $relation, Closure $closure): RepositoryInterface
    {
        $this->model = $this->model->whereHas($relation, $closure);

        return $this;
    }

    /**
     * Find data by multiple values in one field
     *
     * @param string $field
     * @param array  $values
     * @param array  $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWhereIn(string $field, array $values, array $columns = array('*'))
    {
        $this->applyScope();

        $models = $this->model->whereIn($field, $values)->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $models;
    }

    /**
     * Find data by excluding multiple values in one field
     *
     * @param string $field
     * @param array  $values
     * @param array  $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWhereNotIn(string $field, array $values, array $columns = array('*'))
    {
        $this->applyScope();

        $models = $this->model->whereNotIn($field, $values)->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $models;
    }

    /**
     * Find data by between values in one field
     *
     * @param string $field
     * @param array  $values
     * @param array  $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWhereBetween(string $field, array $values, array $columns = array('*'))
    {
        $this->applyScope();

        $models = $this->model->whereBetween($field, $values)->get($columns);

        $this->resetModel();
        $this->resetScope();

        return $models;
    }

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     *
     * @return bool|Model
     */
    public function create(array $attributes)
    {
        DB::beginTransaction();

        try {
            $this->applyScope();

            $model = $this->model->create($attributes);

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return $model;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }

    /**
     * Update a entity in repository by id
     *
     * @param array $attributes
     * @param mixed $id
     *
     * @return bool|Model
     * @throws Exception
     */
    public function update(array $attributes, $id)
    {
        $model = $id;

        if (!($id instanceof Model)) {
            $model = $this->find($id);

            if (!($model instanceof Model)) {
                return false;
            }
        }

        DB::beginTransaction();

        try {
            $this->applyScope();

            $model->update($attributes);

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return $model;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }

    /**
     * Update or Create an entity in repository
     *
     * @param array $attributes
     * @param array $values
     *
     * @return Model|Builder
     * @throws Exception
     */
    public function updateOrCreate(array $attributes, array $values = array())
    {
        $this->applyScope();

        $model = $this->model->updateOrCreate($attributes, $values);

        $this->resetModel();
        $this->resetScope();

        return $model;
    }

    /**
     * Delete a entity in repository by id
     *
     * @param mixed  $id
     * @param string $method
     *
     * @return bool
     * @throws Exception
     */
    public function delete($id, string $method = 'delete'): bool
    {
        $model = $id;

        if (!($id instanceof Model)) {
            if (strtolower(trim($method)) === 'forcedelete') {
                $model = $this->findWithTrashed($id);
            } else {
                $model = $this->find($id);
            }

            if (!($model instanceof Model)) {
                return false;
            }
        }

        DB::beginTransaction();

        try {
            $this->applyScope();

            $model->{$method}();

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }

    /**
     * Order collection by a given column
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): RepositoryInterface
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    /**
     * Load relations
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function with($relations): RepositoryInterface
    {
        $this->model = $this->model->with($relations);

        return $this;
    }

    /**
     * Add sub select queries to count the relations.
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function withCount($relations): RepositoryInterface
    {
        $this->model = $this->model->withCount($relations);

        return $this;
    }

    /**
     * Set hidden fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function hidden(array $fields): RepositoryInterface
    {
        $this->model = $this->model->setHidden($fields);

        return $this;
    }

    /**
     * Set visible fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function visible(array $fields): RepositoryInterface
    {
        $this->model = $this->model->setVisible($fields);

        return $this;
    }

    /**
     * Query Scope
     *
     * @param Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(Closure $scope): RepositoryInterface
    {
        $this->scopeQuery = $scope;

        return $this;
    }

    /**
     * Get first data of repository, or return new Entity
     *
     * @param array $attributes
     *
     * @return Model|Builder
     * @throws Exception
     */
    public function firstOrNew(array $attributes = array())
    {
        $this->applyScope();

        $model = $this->model->firstOrNew($attributes);

        $this->resetModel();
        $this->resetScope();

        return $model;
    }

    /**
     * Get first data of repository, or create new Entity
     *
     * @param array $attributes
     *
     * @return Model|Builder
     * @throws Exception
     */
    public function firstOrCreate(array $attributes = array())
    {
        $this->applyScope();

        $model = $this->model->firstOrCreate($attributes);

        $this->resetModel();
        $this->resetScope();

        return $model;
    }

    /**
     * Trigger method calls to the model
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        return call_user_func_array(array($this->model, $method), $arguments);
    }

    /**
     * Count results of repository with condition
     *
     * @param array  $where
     * @param string $columns
     *
     * @return int
     * @throws Exception
     */
    public function countWhere(array $where, string $columns = '*'): int
    {
        $this->applyScope();

        if ($where) {
            $this->applyConditions($where);
        }

        $result = $this->model->count($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * Count results of repository
     *
     * @param string $columns
     *
     * @return int
     * @throws Exception
     */
    public function count(string $columns = '*'): int
    {
        $this->applyScope();

        $result = $this->model->count($columns);

        $this->resetModel();
        $this->resetScope();

        return $result;
    }

    /**
     * Get all data of repository, simple paginated
     *
     * @param int|null $limit
     * @param array    $columns
     *
     * @return Paginator
     * @throws Exception
     */
    public function simplePaginate(int $limit = null, array $columns = array('*')): Paginator
    {
        return $this->paginate($limit, $columns, "simplePaginate");
    }

    /**
     * Get all data of repository, paginated
     *
     * @param int|null $limit
     * @param array    $columns
     * @param string   $method
     *
     * @return mixed
     * @throws Exception
     */
    public function paginate(?int $limit = null, array $columns = array('*'), string $method = "paginate")
    {
        $this->applyScope();

        $limit = is_null($limit) ? config('repository.pagination.limit', 15) : $limit;
        $results = $this->model->{$method}($limit, $columns);
        $results->appends(app('request')->query());

        $this->resetModel();
        $this->resetScope();

        return $results;
    }

    /**
     * Force delete model
     *
     * @param $id
     *
     * @return bool
     * @throws Exception
     */
    public function forceDelete($id): bool
    {
        return $this->delete($id, 'forceDelete');
    }

    /**
     * Restore Soft Deleted Models
     *
     * @param $id
     *
     * @return bool
     * @throws Exception
     */
    public function restore($id): bool
    {
        $model = $id;

        if (!($id instanceof Model)) {
            $model = $this->findWithTrashed($id);

            if (!($model instanceof Model)) {
                return false;
            }
        }

        DB::beginTransaction();

        try {
            $this->applyScope();

            $model->restore();

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }

    /**
     * Find model by id including soft deleted model
     *
     * @param int|string $id
     * @param array      $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWithTrashed($id, array $columns = array('*'))
    {
        return $this->find($id, $columns, 'withTrashed');
    }

    /**
     * Find soft deleted model by id
     *
     * @param int|string $id
     * @param array      $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findOnlyTrashed($id, array $columns = array('*'))
    {
        return $this->find($id, $columns, 'onlyTrashed');
    }

    /**
     * Get all models including soft deleted models
     *
     * @param array $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function allWithTrashed(array $columns = array('*'))
    {
        return $this->all($columns, 'withTrashed');
    }

    /**
     * Get all soft deleted models
     *
     * @param array $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function allTrashed(array $columns = array('*'))
    {
        return $this->all($columns, 'onlyTrashed');
    }

    /**
     * Insert one or more record into database
     *
     * @param array $values
     *
     * @return bool
     */
    public function insert(array $values): bool
    {
        DB::beginTransaction();

        try {
            $this->applyScope();

            $isInserted = $this->model->insert($values);

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return $isInserted;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }

    /**
     * Retrieve data of repository with limit applied
     *
     * @param int   $limit
     * @param array $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function limit(int $limit, array $columns = array("*"))
    {
        // Shortcut to all with `limit` applied on query via `take`
        $this->take($limit);

        return $this->all($columns);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param int $limit
     *
     * @return $this
     */
    public function take(int $limit): RepositoryInterface
    {
        // Internally `take` is an alias to `limit`
        $this->model = $this->model->limit($limit);

        return $this;
    }

    /**
     * Delete multiple entities by condition.
     *
     * @param array $where
     *
     * @return bool
     */
    public function deleteWhere(array $where): bool
    {
        DB::beginTransaction();

        try {
            $this->applyScope();
            $this->applyConditions($where);

            $deleted = $this->model->delete();

            $this->resetModel();
            $this->resetScope();

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            return false;
        }
    }
}
