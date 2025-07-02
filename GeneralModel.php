<?php
/**
 * GeneralModel.php
 * Model dinamis ala query builder Laravel untuk CI3
 */
class GeneralModel extends CI_Model
{
    protected $table;
    protected $select = '*';
    protected $joins = [];
    protected $wheres = [];
    protected $orWheres = [];
    protected $likes = [];
    protected $orLikes = [];
    protected $likeGroups = [];
    protected $order = '';
    protected $groupBy = null;
    protected $having = null;
    protected $orHaving = null;
    protected $limit = null;
    protected $offset = null;
    protected $cache = [];

    public function tabel($table)
    {
        $this->table = $table;
        return $this;
    }

    public function select($columns = '*')
    {
        $this->select = $columns;
        return $this;
    }

    public function where($column, $value = null)
    {
        $this->wheres[$column] = $value;
        return $this;
    }

    public function or_where($column, $value = null)
    {
        $this->orWheres[$column] = $value;
        return $this;
    }

    public function where_in($column, $values)
    {
        $this->wheres[] = ['in' => [$column, $values]];
        return $this;
    }

    public function where_raw($condition)
    {
        $this->db->where($condition, null, false);
        return $this;
    }

    public function or_where_raw($condition)
    {
        $this->db->or_where($condition, null, false);
        return $this;
    }

    public function like($column, $match)
    {
        $this->likes[$column] = $match;
        return $this;
    }

    public function or_like($column, $match)
    {
        $this->orLikes[$column] = $match;
        return $this;
    }

    public function like_group(array $columns, $keyword)
    {
        $this->likeGroups[] = ['columns' => $columns, 'keyword' => $keyword];
        return $this;
    }

    public function join($table, $condition, $type = '')
    {
        $this->joins[] = [$table, $condition, $type];
        return $this;
    }

    public function order_by($column, $direction = 'ASC')
    {
        $this->order = [$column, $direction];
        return $this;
    }

    public function group_by($columns)
    {
        $this->groupBy = $columns;
        return $this;
    }

    public function having($condition)
    {
        $this->having = $condition;
        return $this;
    }

    public function or_having($condition)
    {
        $this->orHaving = $condition;
        return $this;
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function get()
    {
        $this->_build();
        $query = $this->db->get();
        $this->_reset();
        return $query->result();
    }

    public function first()
    {
        $this->limit(1);
        $this->_build();
        $query = $this->db->get();
        $this->_reset();
        return $query->row();
    }

    public function count()
    {
        $this->_build();
        $count = $this->db->count_all_results();
        $this->_reset();
        return $count;
    }

    public function paginate($perPage = 10, $page = 1)
    {
        $offset = ($page - 1) * $perPage;
        $this->limit($perPage, $offset);

        $data = $this->get();
        $total = $this->tabel($this->table)->count();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function insert($data)
    {
        $this->db->insert($this->table, $data);
        $this->_reset();
        return $this->db->insert_id();
    }

    public function update($data)
    {
        foreach ($this->wheres as $key => $val) {
            if (is_array($val) && isset($val['in'])) {
                $this->db->where_in($val['in'][0], $val['in'][1]);
            } else {
                $this->db->where($key, $val);
            }
        }
        foreach ($this->orWheres as $key => $val) {
            $this->db->or_where($key, $val);
        }
        $this->db->update($this->table, $data);
        $this->_reset();
        return $this->db->affected_rows();
    }

    public function updateOrInsert($where, $data)
    {
        $existing = $this->tabel($this->table)->where($where)->first();
        if ($existing) {
            return $this->tabel($this->table)->where($where)->update($data);
        } else {
            return $this->tabel($this->table)->insert(array_merge($where, $data));
        }
    }

    public function delete()
    {
        foreach ($this->wheres as $key => $val) {
            if (is_array($val) && isset($val['in'])) {
                $this->db->where_in($val['in'][0], $val['in'][1]);
            } else {
                $this->db->where($key, $val);
            }
        }
        foreach ($this->orWheres as $key => $val) {
            $this->db->or_where($key, $val);
        }
        $this->db->delete($this->table);
        $this->_reset();
        return $this->db->affected_rows();
    }

     // Increment field value
    public function increment($column, $amount = 1)
    {
        if (empty($this->db->ar_where)) throw new Exception('increment() butuh where()!');
        $this->db->set($column, "$column + $amount", false);
        $this->db->update($this->table);
        return $this->db->affected_rows();
    }

    // Decrement field value
    public function decrement($column, $amount = 1)
    {
        if (empty($this->db->ar_where)) throw new Exception('decrement() butuh where()!');
        $this->db->set($column, "$column - $amount", false);
        $this->db->update($this->table);
        return $this->db->affected_rows();
    }

    // Upsert many rows at once
    public function upsertMany($rows = [], $keys = [])
    {
        if (empty($rows)) return 0;

        $CI =& get_instance();
        $db = $CI->db;

        $cols = array_keys($rows[0]);
        $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($rows), $placeholders));
        $values = [];
        foreach ($rows as $row) {
            foreach ($cols as $col) {
                $values[] = $row[$col] ?? null;
            }
        }

        $updateSet = array_diff($cols, $keys);
        $updateClause = implode(',', array_map(fn($col) => "`$col`=VALUES(`$col`)", $updateSet));

        $sql = "INSERT INTO `{$this->table}` (`" . implode('`,`', $cols) . "`) VALUES $allPlaceholders ON DUPLICATE KEY UPDATE $updateClause";
        $db->query($sql, $values);
        return $db->affected_rows();
    }

    // Insert ignore
    public function insert_ignore($data)
    {
        $keys = array_keys($data);
        $vals = array_map([$this->db, 'escape'], array_values($data));
        $sql = "INSERT IGNORE INTO `{$this->table}` (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', $vals) . ")";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    // Auto save: insert atau update by primary key
    public function save($data, $primaryKey = 'id')
    {
        if (isset($data[$primaryKey])) {
            return $this->tabel($this->table)->where($primaryKey, $data[$primaryKey])->update($data);
        }
        return $this->tabel($this->table)->insert($data);
    }

    // Pluck satu kolom sebagai array
    public function pluck($column)
    {
        $this->select($column);
        $result = $this->get();
        return array_map(fn($item) => $item->$column, $result);
    }

    // Ambil distinct kolom tertentu
    public function distinct($column)
    {
        $this->db->distinct();
        $this->select($column);
        return $this;
    }

    // Cek apakah data ada
    public function exists()
    {
        $this->limit(1);
        $row = $this->first();
        return !empty($row);
    }

    // Ambil value dari kolom tertentu dari hasil pertama
    public function value($column)
    {
        $row = $this->first();
        return $row ? $row->$column : null;
    }

    // Ambil key => value
    public function keyValue($keyCol, $valCol)
    {
        $this->select("{$keyCol}, {$valCol}");
        $result = $this->get();
        $output = [];
        foreach ($result as $row) {
            $output[$row->$keyCol] = $row->$valCol;
        }
        return $output;
    }

    // Build otomatis untuk DataTables
    public function buildFromDataTables($post, $searchCols = [])
    {
        $keyword = $post['search']['value'] ?? '';
        $start   = (int) ($post['start'] ?? 0);
        $length  = (int) ($post['length'] ?? 10);
        $order   = $post['order'][0] ?? ['column' => 0, 'dir' => 'asc'];
        $columns = $post['columns'] ?? [];

        if ($keyword && $searchCols) {
            $this->like_group($searchCols, $keyword);
        }

        if (isset($columns[$order['column']]['data'])) {
            $col = $columns[$order['column']]['data'];
            $dir = strtolower($order['dir']) === 'desc' ? 'desc' : 'asc';
            $this->order_by($col, $dir);
        }

        $this->limit($length, $start);
        return $this;
    }

    // Relasi manual ala Eager Loading (1 level)
    public function with($relationTable, $foreignKey, $localKey = 'id', $columns = '*')
    {
        $related = $this->db->select($columns)->get($relationTable)->result();
        $map = [];
        foreach ($related as $row) {
            $map[$row->$localKey] = $row;
        }

        $mainData = $this->get();
        foreach ($mainData as &$item) {
            $item->$relationTable = $map[$item->$foreignKey] ?? null;
        }
        return $mainData;
    }

    // Auto cache simple berdasarkan key
    public function cache($key, $ttl = 60)
    {
        if (isset($this->cache[$key])) return $this->cache[$key];

        $CI =& get_instance();
        $CI->load->driver('cache');

        if ($data = $CI->cache->file->get($key)) {
            $this->cache[$key] = $data;
            return $data;
        }

        $data = $this->get();
        $CI->cache->file->save($key, $data, $ttl);
        $this->cache[$key] = $data;
        return $data;
    }

    // Multi-level eager loading nested: 'branches.users.roles'
    public function withDeepNestedMany($relations)
    {
        $mainData = $this->get();

        foreach ($relations as $relationPath => $config) {
            $path = explode('.', $relationPath);
            $relationTable = $config[0];
            $foreignKey    = $config[1];
            $localKey      = $config[2] ?? 'id';
            $columns       = $config[3] ?? '*';

            // Ambil parent level dari path
            $parentLevel = array_slice($path, 0, -1);
            $targetField = end($path);

            // Rekursif injeksi ke data
            $injectNested = function (&$data, $depth = 0) use (&$injectNested, $path, $relationTable, $foreignKey, $localKey, $columns, $targetField) {
                if ($depth === count($path) - 1) {
                    // Ambil semua foreign key dari data ini
                    $ids = array_filter(array_column($data, $localKey));
                    if (empty($ids)) return;

                    $CI =& get_instance();
                    $related = $CI->db->select($columns)->where_in($foreignKey, $ids)->get($relationTable)->result();
                    $grouped = [];
                    foreach ($related as $r) {
                        $grouped[$r->$foreignKey][] = $r;
                    }

                    foreach ($data as &$item) {
                        $item->{$targetField} = $grouped[$item->$localKey] ?? [];
                    }
                } else {
                    $sub = $path[$depth];
                    foreach ($data as &$item) {
                        if (isset($item->$sub) && is_array($item->$sub)) {
                            $injectNested($item->$sub, $depth + 1);
                        }
                    }
                }
            };

            $injectNested($mainData);
        }

        return $mainData;
    }

   // Transaction wrapper: auto rollback on error
    public function transaction(callable $callback)
    {
        $this->db->trans_begin();

        try {
            $result = $callback($this);
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return false;
            }
            $this->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Transaction failed: ' . $e->getMessage());
            return false;
        }
    }

    private function _build()
    {
        $this->db->from($this->table);
        $this->db->select($this->select);

        foreach ($this->joins as [$table, $condition, $type]) {
            $this->db->join($table, $condition, $type);
        }

        foreach ($this->wheres as $key => $val) {
            if (is_array($val) && isset($val['in'])) {
                $this->db->where_in($val['in'][0], $val['in'][1]);
            } else {
                $this->db->where($key, $val);
            }
        }

        foreach ($this->orWheres as $key => $val) {
            $this->db->or_where($key, $val);
        }

        foreach ($this->likes as $key => $val) {
            $this->db->like($key, $val);
        }

        foreach ($this->orLikes as $key => $val) {
            $this->db->or_like($key, $val);
        }

        foreach ($this->likeGroups as $group) {
            $this->db->group_start();
            foreach ($group['columns'] as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $group['keyword']);
                } else {
                    $this->db->or_like($col, $group['keyword']);
                }
            }
            $this->db->group_end();
        }

        if ($this->groupBy) {
            $this->db->group_by($this->groupBy);
        }

        if ($this->having) {
            $this->db->having($this->having);
        }

        if ($this->orHaving) {
            $this->db->or_having($this->orHaving);
        }

        if ($this->order) {
            $this->db->order_by($this->order[0], $this->order[1]);
        }

        if ($this->limit !== null) {
            $this->db->limit($this->limit, $this->offset);
        }
    }

    private function _reset()
    {
        $this->table = null;
        $this->select = '*';
        $this->joins = [];
        $this->wheres = [];
        $this->orWheres = [];
        $this->likes = [];
        $this->orLikes = [];
        $this->likeGroups = [];
        $this->order = '';
        $this->groupBy = null;
        $this->having = null;
        $this->orHaving = null;
        $this->limit = null;
        $this->offset = null;
    }
}


/**contoh penggunaan 
// LIKE group untuk pencarian multi kolom
$this->GeneralModel
    ->tabel('users')
    ->like_group(['name', 'email'], 'admin')
    ->get();

// WHERE IN
$this->GeneralModel
    ->tabel('users')
    ->where_in('id', [1, 2, 3])
    ->get();

// OR LIKE
$this->GeneralModel
    ->tabel('products')
    ->or_like('name', 'kopi')
    ->or_like('description', 'kopi')
    ->get();

// OR HAVING
$this->GeneralModel
    ->tabel('sales')
    ->select('product_id, SUM(qty) AS total_qty')
    ->group_by('product_id')
    ->having('total_qty >', 10)
    ->or_having('product_id', 5)
    ->get();

// Datatables
public function datatables()
{
    $post = $this->input->post();
    $keyword = $post['search']['value'] ?? '';
    $start   = (int) $post['start'];
    $length  = (int) $post['length'];
    $order   = $post['order'][0] ?? ['column' => 0, 'dir' => 'asc'];
    $columns = $post['columns'];
    $orderCol = $columns[$order['column']]['data'];

    $this->load->model('GeneralModel');

    $model = $this->GeneralModel->tabel('users')
        ->select('id, name, email, role')
        ->like_group(['name', 'email', 'role'], $keyword)
        ->order_by($orderCol, $order['dir']);

    $data  = $model->limit($length, $start)->get();
    $total = $this->GeneralModel->tabel('users')
        ->like_group(['name', 'email', 'role'], $keyword)
        ->count();

    echo json_encode([
        'draw'            => intval($post['draw']),
        'recordsTotal'    => $total,
        'recordsFiltered' => $total,
        'data'            => $data,
    ]);
}

Relasi Manual (Eager Load)
$users = $this->GeneralModel->tabel('users')->with('branches', 'branch_id');

⚡ Auto Cache
$data = $this->GeneralModel->tabel('users')->cache('cache_key_users', 120); // 2 menit

*/