# GET {%Name%} - Fetch

`GET {%example_url%}`

## Description

{%description%}

Fetch will find and return the requested object.

## Filters

Filters are passes as query or post params. The following filters are allowed.

{%filter_table%}

{%security%}
## Response

Returns the following attributes.

{%response_table%}


Example response:

    HTTP/1.1 200 OK
    Content-type: application/json
    Content-length: {%example_length%}
    
    {%example_json%}

## Errors

Errors may be returned for security restrictions. Unknown filter parameters are ignored. If
the object is not found then `null` is returned.
