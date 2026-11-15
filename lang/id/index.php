<?php

return [
    'created' => ':resource berhasil ditambahkan.',
    'updated' => ':resource berhasil diperbarui.',
    'deleted' => ':resource berhasil dihapus.',

    'columns' => [
        'active' => 'Aktif',
        'created_at' => 'Dibuat',
        'created_by_id' => 'Dibuat oleh',
        'id' => 'ID',
        'updated_at' => 'Diperbarui',
        'updated_by_id' => 'Diperbarui oleh',
    ],

    'tables' => [
        'branches' => [
            'name' => 'Cabang',
            'columns' => [
                'address' => 'Alamat',
                'code' => 'Kode',
                'name' => 'Nama',
                'phone' => 'Telepon',
            ],
        ],
        'categories' => [
            'name' => 'Kategori',
            'columns' => [
                'name' => 'Nama',
            ],
        ],
        'product_units' => [
            'name' => 'Satuan Barang',
            'columns' => [
                'factor' => 'Jumlah',
                'product_id' => 'Barang',
                'unit_id' => 'Satuan',
            ],
        ],
        'products' => [
            'name' => 'Barang',
            'columns' => [
                'base_unit_id' => 'Satuan terkecil',
                'category_id' => 'Kategori',
                'description' => 'Deskripsi',
                'min_stock' => 'Stok minimum',
                'name' => 'Nama',
                'sku' => 'SKU',
            ],
        ],
        'racks' => [
            'name' => 'Rak',
            'columns' => [
                'code' => 'Kode',
                'name' => 'Nama',
                'warehouse_id' => 'Gudang',
            ],
        ],
        'roles' => [
            'name' => 'Role',
            'columns' => [
                'label' => 'Label',
                'name' => 'Nama',
            ],
        ],
        'units' => [
            'name' => 'Satuan Barang',
            'columns' => [
                'code' => 'Kode',
                'name' => 'Nama',
            ],
        ],
        'users' => [
            'name' => 'Pengguna',
            'columns' => [
                'email' => 'Email',
                'name' => 'Nama',
                'password' => 'Kata sandi',
                'role_id' => 'Role',
                'username' => 'Username',
            ],
        ],
        'warehouses' => [
            'name' => 'Gudang',
            'columns' => [
                'address' => 'Alamat',
                'branch_id' => 'Cabang',
                'code' => 'Kode',
                'name' => 'Nama',
            ],
        ],
    ],
];
