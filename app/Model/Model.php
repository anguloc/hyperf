<?php

declare(strict_types=1);

namespace App\Model;

use App\Util\Logger;
use Hyperf\DbConnection\Model\Model as BaseModel;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Database\Model\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * @method \Hyperf\Database\Model\Model make(array $attributes = [])
 * @method $this withGlobalScope($identifier, $scope)
 * @method $this withoutGlobalScope($scope)
 * @method $this withoutGlobalScopes(array $scopes = null)
 * @method array removedScopes()
 * @method $this whereKey($id)
 * @method $this whereKeyNot($id)
 * @method static $this where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Hyperf\Database\Model\Builder|static orWhere($column, $operator = null, $value = null)
 * @method $this latest($column = null)
 * @method $this oldest($column = null)
 * @method \Hyperf\Database\Model\Collection hydrate(array $items)
 * @method \Hyperf\Database\Model\Collection fromQuery($query, $bindings = [])
 * @method null|\Hyperf\Database\Model\Collection|\Hyperf\Database\Model\Model|static|static[] find($id, $columns = [])
 * @method \Hyperf\Database\Model\Collection findMany($ids, $columns = [])
 * @method \Hyperf\Database\Model\Collection|\Hyperf\Database\Model\Model|static|static[] findOrFail($id, $columns = [])
 * @method \Hyperf\Database\Model\Model|static findOrNew($id, $columns = [])
 * @method \Hyperf\Database\Model\Model|static firstOrNew(array $attributes, array $values = [])
 * @method \Hyperf\Database\Model\Model|static firstOrCreate(array $attributes, array $values = [])
 * @method \Hyperf\Database\Model\Model|static updateOrCreate(array $attributes, array $values = [])
 * @method \Hyperf\Database\Model\Model|static firstOrFail($columns = [])
 * @method \Hyperf\Database\Model\Model|mixed|static firstOr($columns = [], \Closure $callback = null)
 * @method value($column)
 * @method \Hyperf\Database\Model\Collection|static[] get($columns = [])
 * @method \Hyperf\Database\Model\Model[]|static[] getModels($columns = [])
 * @method array eagerLoadRelations(array $models)
 * @method \Generator cursor()
 * @method bool chunkById($count, callable $callback, $column = null, $alias = null)
 * @method \Hyperf\Utils\Collection pluck($column, $key = null)
 * @method paginate(int $perPage = null, array $columns = [], string $pageName = 'page', int $page = null)
 * @method \Hyperf\Contract\PaginatorInterface simplePaginate($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static $this|\Hyperf\Database\Model\Model create(array $attributes = [])
 * @method $this|\Hyperf\Database\Model\Model forceCreate(array $attributes)
 * @method onDelete(\Closure $callback)
 * @method scopes(array $scopes)
 * @method \Hyperf\Database\Model\Builder|static applyScopes()
 * @method $this without($relations)
 * @method \Hyperf\Database\Model\Model|static newModelInstance($attributes = [])
 * @method static \Hyperf\Database\Query\Builder getQuery()
 * @method $this setQuery($query)
 * @method \Hyperf\Database\Query\Builder toBase()
 * @method array getEagerLoads()
 * @method $this setEagerLoads(array $eagerLoad)
 * @method \Hyperf\Database\Model\Model|static getModel()
 * @method $this setModel(\Hyperf\Database\Model\Model $model)
 * @method \Closure getMacro($name)
 * @method bool chunk($count, callable $callback)
 * @method bool each(callable $callback, $count = 1000)
 * @method null|Model|object|static first($columns = [])
 * @method $this|mixed when($value, $callback, $default = null)
 * @method $this|mixed tap($callback)
 * @method $this|mixed unless($value, $callback, $default = null)
 * @method \Hyperf\Database\Model\Builder|static has($relation, $operator = '>=', $count = 1, $boolean = 'and', \Closure $callback = null)
 * @method \Hyperf\Database\Model\Builder|static orHas($relation, $operator = '>=', $count = 1)
 * @method \Hyperf\Database\Model\Builder|static doesntHave($relation, $boolean = 'and', \Closure $callback = null)
 * @method \Hyperf\Database\Model\Builder|static orDoesntHave($relation)
 * @method \Hyperf\Database\Model\Builder|static whereHas($relation, \Closure $callback = null, $operator = '>=', $count = 1)
 * @method \Hyperf\Database\Model\Builder|static orWhereHas($relation, \Closure $callback = null, $operator = '>=', $count = 1)
 * @method \Hyperf\Database\Model\Builder|static whereDoesntHave($relation, \Closure $callback = null)
 * @method \Hyperf\Database\Model\Builder|static orWhereDoesntHave($relation, \Closure $callback = null)
 * @method $this withCount($relations)
 * @method \Hyperf\Database\Model\Builder|static mergeConstraintsFrom(\Hyperf\Database\Model\Builder $from)
 *
 * @see \Hyperf\Database\Model\Builder
 *
 * @method $this select($columns = [])
 * @method \Hyperf\Database\Query\Builder|static selectSub($query, $as)
 * @method \Hyperf\Database\Query\Builder|static selectRaw($expression, array $bindings = [])
 * @method \Hyperf\Database\Query\Builder|static fromSub($query, $as)
 * @method \Hyperf\Database\Query\Builder|static fromRaw($expression, $bindings = [])
 * @method $this addSelect($column)
 * @method $this distinct()
 * @method $this from($table)
 * @method $this join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method \Hyperf\Database\Query\Builder|static joinWhere($table, $first, $operator, $second, $type = 'inner')
 * @method \Hyperf\Database\Query\Builder|static joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method \Hyperf\Database\Query\Builder|static leftJoin($table, $first, $operator = null, $second = null)
 * @method \Hyperf\Database\Query\Builder|static leftJoinWhere($table, $first, $operator, $second)
 * @method \Hyperf\Database\Query\Builder|static leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method \Hyperf\Database\Query\Builder|static rightJoin($table, $first, $operator = null, $second = null)
 * @method \Hyperf\Database\Query\Builder|static rightJoinWhere($table, $first, $operator, $second)
 * @method \Hyperf\Database\Query\Builder|static rightJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method \Hyperf\Database\Query\Builder|static crossJoin($table, $first = null, $operator = null, $second = null)
 * @method mergeWheres($wheres, $bindings)
 * @method array prepareValueAndOperator($value, $operator, $useDefault = false)
 * @method \Hyperf\Database\Query\Builder|static whereColumn($first, $operator = null, $second = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereColumn($first, $operator = null, $second = null)
 * @method $this whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereRaw($sql, $bindings = [])
 * @method $this whereIn($column, $values, $boolean = 'and', $not = false)
 * @method \Hyperf\Database\Query\Builder|static orWhereIn($column, $values)
 * @method \Hyperf\Database\Query\Builder|static whereNotIn($column, $values, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereNotIn($column, $values)
 * @method $this whereIntegerInRaw($column, $values, $boolean = 'and', $not = false)
 * @method $this whereIntegerNotInRaw($column, $values, $boolean = 'and')
 * @method $this whereNull($column, $boolean = 'and', $not = false)
 * @method \Hyperf\Database\Query\Builder|static orWhereNull($column)
 * @method \Hyperf\Database\Query\Builder|static whereNotNull($column, $boolean = 'and')
 * @method $this whereBetween($column, array $values, $boolean = 'and', $not = false)
 * @method \Hyperf\Database\Query\Builder|static orWhereBetween($column, array $values)
 * @method \Hyperf\Database\Query\Builder|static whereNotBetween($column, array $values, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereNotBetween($column, array $values)
 * @method \Hyperf\Database\Query\Builder|static orWhereNotNull($column)
 * @method \Hyperf\Database\Query\Builder|static whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereDate($column, $operator, $value = null)
 * @method \Hyperf\Database\Query\Builder|static whereTime($column, $operator, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereTime($column, $operator, $value = null)
 * @method \Hyperf\Database\Query\Builder|static whereDay($column, $operator, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereDay($column, $operator, $value = null)
 * @method \Hyperf\Database\Query\Builder|static whereMonth($column, $operator, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereMonth($column, $operator, $value = null)
 * @method \Hyperf\Database\Query\Builder|static whereYear($column, $operator, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereYear($column, $operator, $value = null)
 * @method \Hyperf\Database\Query\Builder|static whereNested(\Closure $callback, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder forNestedWhere()
 * @method $this addNestedWhereQuery($query, $boolean = 'and')
 * @method $this whereExists(\Closure $callback, $boolean = 'and', $not = false)
 * @method \Hyperf\Database\Query\Builder|static orWhereExists(\Closure $callback, $not = false)
 * @method \Hyperf\Database\Query\Builder|static whereNotExists(\Closure $callback, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orWhereNotExists(\Closure $callback)
 * @method $this addWhereExistsQuery(self $query, $boolean = 'and', $not = false)
 * @method $this whereRowValues($columns, $operator, $values, $boolean = 'and')
 * @method $this orWhereRowValues($columns, $operator, $values)
 * @method $this whereJsonContains($column, $value, $boolean = 'and', $not = false)
 * @method $this orWhereJsonContains($column, $value)
 * @method $this whereJsonDoesntContain($column, $value, $boolean = 'and')
 * @method $this orWhereJsonDoesntContain($column, $value)
 * @method $this whereJsonLength($column, $operator, $value = null, $boolean = 'and')
 * @method $this orWhereJsonLength($column, $operator, $value = null)
 * @method $this dynamicWhere($method, $parameters)
 * @method $this groupBy($groups)
 * @method $this having($column, $operator = null, $value = null, $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orHaving($column, $operator = null, $value = null)
 * @method \Hyperf\Database\Query\Builder|static havingBetween($column, array $values, $boolean = 'and', $not = false)
 * @method $this havingRaw($sql, array $bindings = [], $boolean = 'and')
 * @method \Hyperf\Database\Query\Builder|static orHavingRaw($sql, array $bindings = [])
 * @method $this orderBy($column, $direction = 'asc')
 * @method $this orderByDesc($column)
 * @method $this inRandomOrder($seed = '')
 * @method $this orderByRaw($sql, $bindings = [])
 * @method \Hyperf\Database\Query\Builder|static skip($value)
 * @method $this offset($value)
 * @method \Hyperf\Database\Query\Builder|static take($value)
 * @method $this limit($value)
 * @method \Hyperf\Database\Query\Builder|static forPage($page, $perPage = 15)
 * @method \Hyperf\Database\Query\Builder|static forPageAfterId($perPage = 15, $lastId = 0, $column = 'id')
 * @method \Hyperf\Database\Query\Builder|static union($query, $all = false)
 * @method \Hyperf\Database\Query\Builder|static unionAll($query)
 * @method $this lock($value = true)
 * @method \Hyperf\Database\Query\Builder lockForUpdate()
 * @method \Hyperf\Database\Query\Builder sharedLock()
 * @method static string toSql()
 * @method int getCountForPagination($columns = [])
 * @method string implode($column, $glue = '')
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static int count($columns = '*')
 * @method static min($column)
 * @method static max($column)
 * @method static sum($column)
 * @method static avg($column)
 * @method static average($column)
 * @method aggregate($function, $columns = [])
 * @method float|int numericAggregate($function, $columns = [])
 * @method static bool insert(array $values)
 * @method static int insertGetId(array $values, $sequence = null)
 * @method bool insertUsing(array $columns, $query)
 * @method insertOrIgnore(array $values)
 * @method bool updateOrInsert(array $attributes, array $values = [])
 * @method truncate()
 * @method \Hyperf\Database\Query\Expression raw($value)
 * @method static array getBindings()
 * @method array getRawBindings()
 * @method $this setBindings(array $bindings, $type = 'where')
 * @method $this addBinding($value, $type = 'where')
 * @method $this mergeBindings(self $query)
 * @method \Hyperf\Database\Query\Processors\Processor getProcessor()
 * @method \Hyperf\Database\Query\Grammars\Grammar getGrammar()
 * @method $this useWritePdo()
 * @method static cloneWithout(array $properties)
 * @method static cloneWithoutBindings(array $except)
 * @method macroCall($method, $parameters)
 * @method static macro($name, $macro)
 * @method static mixin($mixin)
 * @method static bool hasMacro($name)
 *
 * @see \Hyperf\Database\Query\Builder
 * Class Model
 * @package App\Model
 */
abstract class Model extends BaseModel
{
//    use Cacheable;

    use SoftDeletes;

    protected $dateFormat = "U";

    const CREATED_AT = 'add_time';
    const UPDATED_AT = 'update_time';
    const DELETED_AT = 'is_deleted';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(array $attributes = [])
    {
        $this->logger = Logger::get('model:', MODEL_LOG);
        parent::__construct($attributes);
    }

    public function save(array $options = []): bool
    {
        try {
            return parent::save($options);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'class' => static::class,
                'method' => __FUNCTION__,
            ]);
            return false;
        }
    }


    /**
     * @param array $data
     * @param array|null $update_columns 没有这个就是批量插入
     * @return bool
     */
    public static function insertOnDuplicateKey(array $data, array $update_columns = null)
    {
        if (empty($data)) {
            return false;
        }

        $data = array_values($data);
        $first = current($data);
        $fields = array_keys($first);
        $field_str = "(`" . implode("`,`", $fields) . "`)";

        $obj = new static();
        $table = $obj->getConnection()->getQueryGrammar()->getTablePrefix() . $obj->getTable();

        $sql = 'INSERT INTO `' . $table . '`' . $field_str . ' VALUES';
        $questionMarks = array_fill(0, count($first), '?');
        $line = '(' . implode(',', $questionMarks) . ')';
        $lines = array_fill(0, count($data), $line);
        $sql .= implode(', ', $lines);

        if (!empty($update_columns)) {
            $update_arr = [];
            // TODO 这里需要改成PDO模式
            if (is_array($update_columns) && !empty($update_columns)) {
                foreach ($update_columns as $field => $value) {
                    $str = "`{$field}`=";
                    if (is_array($value) && isset($value['original_value'])) {
                        $str .= $value['original_value'];
                    } else {
                        $str .= "'{$value}'";
                    }
                    $update_arr[] = $str;
                }
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(",", $update_arr);
        }

        $data = call_user_func_array('array_merge', array_map(function ($items) {
            $result = [];
            foreach ($items as $item) {
                if ($tmp = filter_var($item, FILTER_VALIDATE_INT)) {
                    $result[] = $tmp;
                } else {
                    $result[] = $item;
                }
            }
            return $result;
        }, $data));

        return $obj->getConnection()->affectingStatement($sql, $data);
    }




}
