# 📘 GeneralModel for CodeIgniter 3

Model dinamis gaya Laravel Query Builder untuk CI3, mendukung fitur-fitur kompleks seperti transaksi, relasi bertingkat, upsert massal, dan lainnya, Compatible `PHP 5.6 s.d 8.0`.

---

## ✅ Fitur Utama
| Fitur                                   | Deskripsi                                             |
| --------------------------------------- | ----------------------------------------------------- |
| `withDeepNestedMany()`                  | Relasi one-to-many bertingkat otomatis (nested)       |
| `transaction()`                         | Transaksi DB otomatis dengan rollback on error        |
| `increment() / decrement()`             | Tambah/kurangi nilai kolom langsung di DB             |
| `upsertMany()`                          | Bulk insert dengan ON DUPLICATE KEY UPDATE            |
| Integrasi DataTables                    | Query builder kompatibel untuk datatables server-side |
| Soft Delete                             | Nonaktifkan data tanpa hapus fisik                    |
| `remember()` / `cacheForget()`          | Auto caching query                                    |
| `paginate()`                            | Pagination otomatis                                   |
| `count() / sum() / avg()`               | Fungsi agregat bawaan                                 |
| `likeGroup()`                           | LIKE multiple kolom sekaligus dengan grouping         |
| `orWhere() / orLike()`                  | Pencarian fleksibel                                   |
| `whereIn()` / `orHaving()` / `having()` | Kondisi lanjutan untuk query builder                  |

## Contoh Penggunaan

### 1. withDeepNestedMany()

**Relasi one-to-many bertingkat otomatis.**

```php
$this->GeneralModel->tabel('branches')->withDeepNestedMany([
  'users' => ['users', 'branch_id', 'id'],
  'users.orders' => ['orders', 'user_id', 'id'],
]);
```

Contoh skema relasi:

* `branches` memiliki banyak `users`
Hasil akan memiliki struktur nested seperti:

```php
[
  {
    id: 1,
    name: 'Branch A',
    users: [
      { id: 10, name: 'User A', orders: [
          { id: 1001, total: 50000 },
          { id: 1002, total: 30000 },
        ]
      },
      ...
    ]
  },
  ...
]
```

* `users` memiliki banyak `orders`
```php
$this->GeneralModel->tabel('branches')->withDeepNestedMany([
  'users' => ['users', 'branch_id', 'id'],
  'users.roles' => ['roles', 'user_id', 'id'],
]);
````

Hasil akan memiliki struktur nested seperti:

```php
[
  {
    id: 1,
    name: 'Branch A',
    users: [
      { id: 10, name: 'User A', roles: [ ... ] },
      ...
    ]
  },
  ...
]
```

---

### 2. transaction(callable \$callback)

**Membungkus banyak query dalam transaksi database otomatis.**

```php
$this->GeneralModel->transaction(function($model) {
  $model->tabel('users')->insert([...]);
  $model->tabel('logs')->insert([...]);
});
```

Akan rollback jika terjadi error/exception.

---

### 3. increment() dan decrement()

**Update numerik langsung di query tanpa select terlebih dahulu.**

```php
$this->GeneralModel->tabel('products')->where('id', 5)->increment('stock', 10);
$this->GeneralModel->tabel('products')->where('id', 5)->decrement('stock', 3);
```

---

### 4. upsertMany()

**Insert banyak data sekaligus dengan dukungan `ON DUPLICATE KEY UPDATE`.**

```php
$this->GeneralModel->tabel('users')->upsertMany([
  ['id' => 1, 'name' => 'A'],
  ['id' => 2, 'name' => 'B']
], ['id']);
```

Jika `id` sudah ada → data akan di-*update*, bukan insert baru.

---

### 5. Integrasi dengan DataTables Server-Side

**Buat query untuk kebutuhan DataTables otomatis dari request frontend.**

--BackEnd
```php
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
```

--Frontend JavaScript Jquery Datatables
```js
$('#userTable').DataTable({
  processing: true,
  serverSide: true,
  ajax: {
    url: '/user/datatables',
    type: 'POST'
  },
  columns: [
    { data: 'id' },
    { data: 'name' },
    { data: 'email' },
    { data: 'role' }
  ]
});
```
---

### 6. Soft Delete, Restore, Force Delete

**Menandai data sebagai terhapus tanpa benar-benar menghapus dari database.**

```php
$this->GeneralModel->tabel('users')->where('id', 1)->softDelete();
$this->GeneralModel->tabel('users')->withTrashed()->get();
$this->GeneralModel->tabel('users')->onlyTrashed()->restore();
$this->GeneralModel->tabel('users')->onlyTrashed()->forceDelete();
```

---

### 7. Auto Caching Query

**Simpan hasil query ke cache untuk menghemat load database.**

```php
$data = $this->GeneralModel->tabel('branches')->remember(300)->get(); // cache 5 menit
$this->GeneralModel->cacheForget('branches'); // hapus cache secara manual
```

---

## 🔗 Contoh Relasi Manual (One to Many)

Kamu juga bisa membangun relasi manual satu ke banyak (1-n) secara eksplisit:

```php
// Ambil semua branch
$branches = $this->GeneralModel->tabel('branches')->get();
$branchIds = array_column($branches, 'id');

// Ambil user berdasarkan branch
$users = $this->GeneralModel->tabel('users')->whereIn('branch_id', $branchIds)->get();

// Gabungkan secara manual
foreach ($branches as &$branch) {
  $branch['users'] = array_values(array_filter($users, function($u) use ($branch) {
    return $u['branch_id'] == $branch['id'];
  }));
}
```

Gunakan pendekatan ini jika tidak ingin memakai `withDeepNestedMany()`.

---

## 🛠 Cara Pakai Dasar

### Select data

```php
$data = $this->GeneralModel->tabel('users')->where('role', 'admin')->get();
```

### Ambil satu data saja

```php
$data = $this->GeneralModel->tabel('users')->where('id', 1)->first();
```

### Insert data

```php
$this->GeneralModel->tabel('users')->insert([
  'name' => 'John',
  'email' => 'john@example.com'
]);
```

### Update data

```php
$this->GeneralModel->tabel('users')->where('id', 1)->update([
  'email' => 'new@example.com'
]);
```

### Delete data

```php
$this->GeneralModel->tabel('users')->where('id', 1)->delete();
```

---

## 📌 Catatan Tambahan

* Gunakan `->debug()` di akhir query chain untuk mencetak SQL dan value-nya.
* Semua fungsi `where`, `orWhere`, `orLike`, `groupBy`, `having`, `likeGroup`, `whereIn`, `orHaving`, dsb tersedia.
* Auto-pagination tersedia via `->paginate($page, $perPage)`.
* Fungsi agregat: `count()`, `sum()`, `avg()` juga didukung.

---

## 📂 Struktur Rekomendasi CI3

* lokasi model: `application/models/GeneralModel.php`
* Load otomatis via `application/config/autoload.php`.
```php
$autoload['model'] = array('GeneralModel');
```
* Load secara manual di controller:
```php
$this->load->model('GeneralModel'); //untuk load manual
```
* tips penggunaan simple
```php
$DB = $this->GeneralModel;
$DB->tabel('users')
->where('id', 1)
->update([
  'email' => 'new@example.com'
]);
```
---
## Saran Gunakan CI3 yang Sudah Di-patch untuk PHP 8
Kamu bisa pakai:
🔗 [https://github.com/codeigniter-id/CodeIgniter-3.1.13.](https://codeigniter.com/userguide3/installation/downloads.html)
atau fork resmi yang diperbarui komunitas, misal: [kenjis/ci3-to-4-upgrade-helper](https://github.com/kenjis/ci3-to-4-upgrade-helper)

![CI3](https://img.shields.io/badge/framework-CodeIgniter3-red)

Maintained by: **@awenk**
