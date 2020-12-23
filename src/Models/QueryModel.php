<?php

namespace CI4Restful\Models;

use CodeIgniter\Model;

class QueryModel extends Model
{

    protected $table;

    protected $primaryKey = 'id';

    protected $returnType = 'object';
    protected $useSoftDeletes = true;

    protected $allowedFields = [];

    protected $useTimestamps = false;

    protected $validationRules = [];
    protected $validationMessages = [];

    protected $skipValidation = false;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function insertToDb($data = [])
    {
        $allowedFields = [];

        foreach ($data as $key => $value) {
            array_push($allowedFields, $key);
        }

        $this->allowedFields = $allowedFields;

        return $this->insert($data);
    }


    public function updateDb($data = [], $where)
    {
        $allowedFields = [];

        foreach ($data as $key => $value) {
            array_push($allowedFields, $key);
        }

        $this->allowedFields = $allowedFields;

        if (is_array($where)) {
            foreach ($where as $key => $value) {
                $this->where($key, $value);
            }
        }

        $this->set($data);

        return $this->update();
    }

    public function getDataById($where)
    {

        if (is_array($where)) {
            foreach ($where as $key => $value) {
                $this->where($key, $value);
            }
        } else {
            $this->where('id', $where);
        }

        $result = $this->get()->getResult();

        if ($result != null) {
            return $result;
        }

        return [];
    }

    public function getData($limit = ["limit" => 20, "offset" => 0], $where = "", $Order_by = "id")
    {

        if (is_array($where)) foreach ($where as $key => $value) $this->where($key, $value);

        $this->orderBy($Order_by, "DESC");

        if (is_array($limit))  $this->limit($limit["limit"], $limit["offset"]);
        if (!is_array($limit) && !empty($limit)) $this->limit($limit);

        $result = $this->get()->getResult();

        if ($result != null) {
            return [
                'data' => $result,
                'count_all' => $this->countAllResults(),
            ];
        }

        return false;
    }

    public function getDataBySearch($keywords = [], $limit = ["limit" => 10, "offset" => 0], $where = "", $Order_by = "id")
    {

        $this->orderBy($Order_by, "DESC");

        if (is_array($limit))  $this->limit($limit["limit"], $limit["offset"]);
        if (!is_array($limit) && !empty($limit)) $this->limit($limit);

        if (is_array($keywords)) :
            foreach ($keywords as $key => $value)
                $this->like($key, $value, 'both');
        endif;

        if (is_array($where)) :
            foreach ($where as $key => $value)
                $this->where($key, mb_strtolower($value, 'UTF-8'));
        endif;

        $result = $this->get()->getResult();

        if ($result != null) {
            return [
                'data' => $result,
                'count_all' => $this->countAllResults(),
            ];
        }

        return false;
    }
}