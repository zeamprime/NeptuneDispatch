# {%METHOD%} {%Name%} - {%OpName%}

## Description

{%Name%} represents a thing that does something.

{%OpName%} will do xyz and return something.

## Parameters

| Parameter   | Type     | Opt? | Description                                                   |
|-------------|----------|------|---------------------------------------------------------------|
| a           | Num      | No   | Set a |


## Filters

Filters are passes as query or post params. The following filters are allowed.

| Parameter   | Description                         | Example                    |
|-------------|-------------------------------------|----------------------------|
| order       | Sort the list by the given key. Specify - for descending, + or blank for ascending. | order=+name
| rel         | True/false. Include related objects. | rel=true

## Response

Returns the following keys.

| Field       | Type    | Description                         |
|-------------|---------|-------------------------------------|
| id          | Num     |                                     |


Example response:

    HTTP/1.1 200 OK
    Content-type: text/json
    Content-length: 000
    
    {"{%name%}": {"a": 123}}

## Errors

Errors may be returned for missing or unexpected fields or fields with an invalid value.

Additionally the following errors may be returned:

| Field       | Error                           | Description                                    |
|-------------|---------------------------------|------------------------------------------------|
| id          | IdentifierInUse                 | The given ID is not unique. |
