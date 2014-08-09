# GET {%Names%} - Index

`GET {%example_url%}`

## Description

{%description%}

Index will list all the {%Names%} that the requesting user can see.

## Filters

Filters are passes as query or post params. The following filters are allowed.

{%filter_table%}

{%security%}
## Response

Returns an array of {%Name%} objects.

Example response:

    HTTP/1.1 200 OK
    Content-type: application/json
    Content-length: {%example_length%}
    
    {%example_json%}

## Errors

Errors may be returned for security restrictions. Unknown filter parameters are ignored.
