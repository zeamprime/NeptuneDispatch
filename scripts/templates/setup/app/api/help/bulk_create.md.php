<?php
/**
 * (c) 2013 Everett Morse.
 * 
 * Template for Bulk API call. Just explains a bit and redirects.
 */
?>
# POST <?=$this->displayName?> - Bulk Create

## Description

Iterates over an array of input values performing individual operations on each one. The results of each operation are returned in an array.

See [<?=$this->displayName?> Create](<?=Page::absPath("/help/".$this->modelName."/post.".$this->format)?>) for a description of parameters and return values.

## Request

Example request body:

    [{"data": "here1"},
     {"data": "here2"},
     {"data": "here3"}]


## Response


Example response body:

    [{"<?=$this->modelName?>": {"id": 1, "data": "here1"}},
     {"<?=$this->modelName?>": {"id": 2, "data": "here2"}},
     {"<?=$this->modelName?>": {"id": 3, "data": "here3"}}]

