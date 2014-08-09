<?php
/**
 * (c) 2013 Everett Morse.
 * 
 * Template for Bulk API call. Just explains a bit and redirects.
 */
?>
# POST <?=$this->displayName?> - Bulk Update

## Description

Iterates over an array of IDs performing the delete operation on each one. The results of each operation are returned in an array.  The expected input is just an object with key `ids` giving an array of IDs to delete.

Note that query/post params apply to all of the operations. There is no way to set different parameters for each individual object to delete.

See [<?=$this->displayName?> Delete](<?=Page::absPath("/help/".$this->modelName."/delete.".$this->format)?>) for a description of parameters and return values.

## Request

Example request body:

    {"ids": [1, 2, 3]}}


## Response


Example response body:

    [{"status": true},
     {"status": true}},
     {"status": true}]

