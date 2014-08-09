# PUT {%Name%} - Update

`PUT {%example_url%}`

## Description

{%description%}

Updates a {%Name%}.

## Parameters

{%param_table%}

{%security%}
## Response

If successful, returns the object.

Example response:

    HTTP/1.1 200 OK
    Content-type: application/json
    Content-length: {%example_length%}
    
    {%example_json%}

## Errors

Errors may be returned for security, missing or unexpected fields, or fields with an 
invalid value.  The following validation errors may be returned:

{%error_table%}
