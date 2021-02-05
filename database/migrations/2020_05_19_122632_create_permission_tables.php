<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding.');
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('role_id');

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        \Illuminate\Support\Facades\DB::table('permissions')->insert(
            array(
                [
                    'name' => 'create themes',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'edit groups',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'edit permissions',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'complete theme',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'disable menu',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'set password',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'view priorities',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'share theme',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'view procedures',
                    'guard_name' => 'web'
                ],
                [
                    'name' => 'edit users',
                    'guard_name' => 'web'
                ],
            )
        );

        \Illuminate\Support\Facades\DB::table('roles')->insert(
            array(
                [
                    'name' => 'Admin',
                    'guard_name' => 'web'
                ])
        );

        \Illuminate\Support\Facades\DB::table('role_has_permissions')->insert(
            array(
                [
                    'permission_id' => '3',
                    'role_id' => '1'
                ],
                [
                    'permission_id' => '10',
                    'role_id' => '1'
                ]
            )
        );

        \Illuminate\Support\Facades\DB::table($tableNames['model_has_roles'])->insert([
            [
                'role_id' => 1,
                'model_type'    => 'App\Model\User',
                'model_id'  => 1
            ]
        ]);

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
}
