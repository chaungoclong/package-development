<?php

namespace Longtnt\BaseRepository\Repositories\Contracts;

use Closure;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Interface BaseRepository
 * PHP: 7.1+, Laravel: 5.8+
 */
interface BaseRepository
{
    /**
     * Trigger static method calls to the model
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments);

    /**
     * Get data array for populate field select
     *
     * @param string      $column
     * @param string|null $key
     *
     * @return Collection
     */
    public function pluck(string $column, ?string $key = null): Collection;

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
    public function sync($id, string $relation, $attributes, bool $detaching = true): array;

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
    public function syncWithoutDetaching($id, string $relation, $attributes): array;

    /**
     * Get all data of repository
     *
     * @param array  $columns
     * @param string $trashed
     *
     * @return mixed
     * @throws Exception
     */
    public function all(array $columns = array('*'), string $trashed = '');

    /**
     * Count results of repository
     *
     * @param string $columns
     *
     * @return int
     */
    public function count(string $columns = '*'): int;

    /**
     * Count results of repository with condition
     *
     * @param array  $where
     * @param string $columns
     *
     * @return int
     */
    public function countWhere(array $where, string $columns = '*'): int;

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
    public function paginate(?int $limit = null, array $columns = array('*'), string $method = "paginate");

    /**
     * Get all data of repository, simple paginated
     *
     * @param int|null $limit
     * @param array    $columns
     *
     * @return Paginator
     * @throws Exception
     */
    public function simplePaginate(int $limit = null, array $columns = array('*')): Paginator;

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
    public function find($id, array $columns = array('*'), string $trashed = '');

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
    public function findByField(string $field, $value, array $columns = array('*'));

    /**
     * Find data by multiple fields
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWhere(array $where, array $columns = array('*'));

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
    public function findWhereIn(string $field, array $values, array $columns = array('*'));

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
    public function findWhereNotIn(string $field, array $values, array $columns = array('*'));

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
    public function findWhereBetween(string $field, array $values, array $columns = array('*'));

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     *
     * @return bool|Model
     */
    public function create(array $attributes);

    /**
     * Update a entity in repository by id
     *
     * @param array $attributes
     * @param mixed $id
     *
     * @return bool|Model
     * @throws Exception
     */
    public function update(array $attributes, $id);

    /**
     * Update or Create an entity in repository
     *
     * @param array $attributes
     * @param array $values
     *
     * @return Model|Builder
     * @throws Exception
     */
    public function updateOrCreate(array $attributes, array $values = array());

    /**
     * Delete a entity in repository by id
     *
     * @param mixed  $id
     * @param string $method
     *
     * @return bool
     * @throws Exception
     */
    public function delete($id, string $method = 'delete'): bool;

    /**
     * Order collection by a given column
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): BaseRepository;

    /**
     * Load relations
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function with($relations): BaseRepository;

    /**
     * Load relation with closure
     *
     * @param string  $relation
     * @param closure $closure
     *
     * @return $this
     */
    public function whereHas(string $relation, Closure $closure): BaseRepository;

    /**
     * Add sub select queries to count the relations.
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function withCount($relations): BaseRepository;

    /**
     * Set hidden fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function hidden(array $fields): BaseRepository;

    /**
     * Set visible fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function visible(array $fields): BaseRepository;

    /**
     * Query Scope
     *
     * @param Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(Closure $scope): BaseRepository;

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope(): BaseRepository;

    /**
     * Get first data of repository, or return new Entity
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function firstOrNew(array $attributes = array());

    /**
     * Get first data of repository, or create new Entity
     *
     * @param array $attributes
     *
     * @return mixed
     */
    public function firstOrCreate(array $attributes = array());

    /**
     * Trigger method calls to the model
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $method, array $arguments);

    /**
     * Force delete model
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forceDelete($id): bool;

    /**
     * Restore Soft Deleted Models
     *
     * @param $id
     *
     * @return bool
     */
    public function restore($id): bool;

    /**
     * Find model by id including soft deleted model
     *
     * @param int|string $id
     * @param array      $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findWithTrashed($id, array $columns = array('*'));

    /**
     * Find soft deleted model by id
     *
     * @param int|string $id
     * @param array      $columns
     *
     * @return mixed
     * @throws Exception
     */
    public function findOnlyTrashed($id, array $columns = array('*'));

    /**
     * Get all models including soft deleted models
     *
     * @param array $columns
     *
     * @return mixed
     */
    public function allWithTrashed(array $columns = array('*'));

    /**
     * Get all soft deleted models
     *
     * @param array $columns
     *
     * @return mixed
     */
    public function allTrashed(array $columns = array('*'));

    /**
     * Insert one or more record into database
     *
     * @param array $values
     *
     * @return bool
     */
    public function insert(array $values): bool;

    /**
     * Retrieve data of repository with limit applied
     *
     * @param int   $limit
     * @param array $columns
     *
     * @return mixed
     */
    public function limit(int $limit, array $columns = array("*"));

    /**
     * Set the "limit" value of the query.
     *
     * @param int $limit
     *
     * @return $this
     */
    public function take(int $limit): BaseRepository;

    /**
     * Delete multiple entities by condition.
     *
     * @param array $where
     *
     * @return bool
     */
    public function deleteWhere(array $where): bool;
}
