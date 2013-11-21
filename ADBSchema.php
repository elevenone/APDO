<?php

/*
 * http://aeqdev.com/tools/php/apdo/
 * v 0.1
 *
 * Copyright © 2013 Krylosov Maksim <Aequiternus@gmail.com>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace aeqdev;

/**
 *
 */
class ADBSchema extends APDO
{

    public $prefix;

    protected $tables = [];

    public function __get($name)
    {
        if (!isset($this->tables[$name])) {
            $classTable = $this->{'class_' . $name};
            $this->tables[$name] = new $classTable($this);
        }
        return $this->tables[$name];
    }

    public function __call($name, $args)
    {
        return $this->{$name}->statement();
    }

    public function statement($statement = null, $args = null)
    {
        return new ADBSchemaStatement($this->parameters, $statement, $args);
    }

}

/**
 *
 */
class ADBSchemaTable
{

    /**
     * @var \aeqdev\ADBSchema
     */
    public $schema;

    public $name;
    public $pkey;
    public $ukey;
    public $fkey;

    protected $class_row;

    public function __construct(ADBSchema $schema)
    {
        $this->schema = $schema;
    }

    public function statement() {
        return $this->schema->statement()
            ->schemaTable($this)
            ->fetchMode(
                \PDO::FETCH_CLASS,
                $this->class_row,
                [$this]
            );
    }

    public function create()
    {
        $classRow = $this->class_row;
        return new $classRow($this, true);
    }

    public function get($id)
    {
        return $this->statement()
            ->key($id)
            ->fetchOne();
    }

}

/**
 *
 */
class ADBSchemaStatement extends APDOStatement
{

    protected $schemaTable;

    public function schemaTable(ADBSchemaTable $table)
    {
        $this->schemaTable = $table;
        return $this->table($table->schema->prefix . $table->name)
            ->pkey($table->pkey);
    }

    public function refs(&$data)
    {
        if (empty($data)) {
            return $this->nothing();
        }

        $itemTable = $this->getDataTable($data);

        if (isset($itemTable->fkey[$this->schemaTable->name])) {
            return $this->referrers(
                $data,
                $itemTable->name,
                $this->schemaTable->name,
                $itemTable->fkey[$this->schemaTable->name],
                $itemTable->pkey,
                isset($itemTable->ukey[$itemTable->fkey[$this->schemaTable->name]])
            );
        } else if (isset($this->schemaTable->fkey[$itemTable->name])) {
            return $this->references(
                $data,
                $this->schemaTable->name,
                $itemTable->name,
                $this->schemaTable->fkey[$itemTable->name],
                $itemTable->pkey,
                isset($this->schemaTable->ukey[$this->schemaTable->fkey[$itemTable->name]])
            );
        }
    }

    /**
     * @return \aeqdev\ADBSchemaTable
     */
    protected function getDataTable(&$data)
    {
        return is_array($data)
            ? reset($data)->table()
            : $data->table();
    }

}

/**
 *
 */
abstract class ADBSchemaRow
{

    protected $table;
    protected $new;

    public function table()
    {
        return $this->table;
    }

    public function __construct(ADBSchemaTable $table, $new = false)
    {
        $this->table = $table;
        $this->new = $new;
    }

    public function __call($name, $args)
    {
        /* @var $statement \aeqdev\ADBSchemaStatement */
        $statement = $this->table->schema->{$name}()->refs($this);
        /* @var $refTable \aeqdev\ADBSchemaTable */
        $refTable = $this->table->schema->{$name};

        return isset($refTable->fkey[$this->table->name])
            && !isset($refTable->ukey[$refTable->fkey[$this->table->name]])
            ? $statement->fetchAll()
            : $statement->fetchOne();
    }

    public function save()
    {
        $this->validate();

        if ($this->new) {
            $id = $this->table->statement()
                ->insert($this);
            if (isset($id)) {
                $this->{$this->table->pkey} = $id;
            }
            $this->new = false;
        } else {
            $this->table->statement()
                ->key($this->{$this->table->pkey})
                ->update($this);
        }
    }

    public function validate()
    {

    }

}