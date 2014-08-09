<?php
/**
 * (c) 2013 Everett Morse.
 * 
 * Template for Bulk API call. Just explains a bit and redirects.
 */
?>
# POST <?=$this->displayName?> - Bulk Update

## Description

Iterates over an array of input values performing individual operations on each one. The results of each operation are returned in an array.

Since `update` takes both an ID and a data node, input is an object containing two arrays: `ids` and `roots`.

See [<?=$this->displayName?> Update](<?=Page::absPath("/help/".$this->modelName."/put.".$this->format)?>) for a description of parameters and return values.

## Request

Example request body:

    {"ids": [1, 2, 3],
     "roots": [{"<?=$this->modelName?>": {"id": 1, "data": "new value1"}},
               {"<?=$this->modelName?>": {"id": 1, "data": "new value2"}},
               {"<?=$this->modelName?>": {"id": 1, "data": "new value3"}}]
    }


## Response


Example response body:

    [{"status": true},
     {"status": true}},
     {"status": true}]

