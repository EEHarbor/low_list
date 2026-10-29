window.doc_page = {
    "addon": "Low List",
    "title": "Tag Reference",
    "desc": "<p>Loop over a separated list, or conditionally return content when it contains a value. All examples below are literal template code. See <a href=\"/low-list/documentation/installation\">Installation</a> for setup.</p>",
    "terms": [
        {
            "label": "required",
            "description": "Supply a non-empty value for this parameter."
        }
    ],
    "sections": [
        {
            "type": "tagtoc"
        },
        {
            "type": "tags"
        }
    ],
    "tags": [
        {
            "tag": "{exp:low_list:each}",
            "shortname": "exp_low_list_each",
            "desc": "Loop through character-separated items and return the contents of the tag pair once for each item.",
            "sections": [
                {
                    "type": "params",
                    "title": "Parameters",
                    "items": [
                        {
                            "item": "items",
                            "type": "string",
                            "desc": "The list to loop through, separated by `sep`. Use a colon (`:`) within an item to separate its key and value.",
                            "default": "",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:each items=\"north|east|south|west\" as=\"direction\"}\n    You can go {direction}.\n{/exp:low_list:each}"
                                }
                            ]
                        },
                        {
                            "item": "as",
                            "type": "string",
                            "desc": "Name the variables available inside the pair. The default `key:val` provides `{key}` and `{val}`. Supply two names separated by a colon for key/value pairs, or one name when you only need the value.",
                            "default": "key:val",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:each items=\"asc:Ascending|desc:Descending\" as=\"value:label\"}\n    <label>\n        <input type=\"radio\" name=\"sort\" value=\"{value}\">\n        {label}\n    </label>\n{/exp:low_list:each}"
                                }
                            ]
                        },
                        {
                            "item": "sep",
                            "type": "string",
                            "desc": "The string that separates the items. Supply a non-empty separator; colons still separate keys and values within each item.",
                            "default": "|",
                            "examples": [
                                {
                                    "tag_example": "<label for=\"orderby_sort\">Sort by</label>\n<select id=\"orderby_sort\" name=\"orderby_sort\">\n    {exp:low_list:each items=\":Relevance;date|asc:Publish date;title|asc:Title\" sep=\";\"}\n        <option value=\"{key}\">{val}</option>\n    {/exp:low_list:each}\n</select>"
                                }
                            ]
                        },
                        {
                            "item": "range",
                            "type": "string",
                            "desc": "Set to `yes` to treat exactly two items as the start and end of a range. The example counts down from 99 to 0.",
                            "default": "no",
                            "accepts": "yes, no",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:each items=\"99|0\" range=\"yes\"}\n    {val} bottle{if val != 1}s{/if} of beer on the wall.\n{/exp:low_list:each}"
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        {
            "tag": "{exp:low_list:has}",
            "shortname": "exp:low_list:has",
            "desc": "Return the contents of the tag pair when the value is present in the list. Otherwise, return an empty string.",
            "sections": [
                {
                    "type": "params",
                    "title": "Parameters",
                    "items": [
                        {
                            "item": "value",
                            "type": "string",
                            "desc": "The non-empty value to search for in the list.",
                            "required": true,
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:has value=\"spork\" items=\"knife|fork|spoon|spork\"}\n    We have a spork!\n{/exp:low_list:has}"
                                }
                            ]
                        },
                        {
                            "item": "items",
                            "type": "string",
                            "desc": "The list to search, separated by `sep`.",
                            "default": "",
                            "examples": [
                                {
                                    "tag_example": "<label for=\"category\">Categories</label>\n<select id=\"category\" name=\"category[]\" multiple>\n    {exp:channel:categories style=\"linear\"}\n        <option value=\"{category_id}\"{exp:low_list:has value=\"{category_id}\" items=\"1|2|3\"} selected{/exp:low_list:has}>\n            {category_name}\n        </option>\n    {/exp:channel:categories}\n</select>"
                                }
                            ]
                        },
                        {
                            "item": "sep",
                            "type": "string",
                            "desc": "The non-empty string separating each item.",
                            "default": "|",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:has value=\"spork\" sep=\",\" items=\"knife,fork,spoon,spork\"}\n    We have a spork!\n{/exp:low_list:has}"
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        {
            "tag": "{exp:low_list:has_not}",
            "shortname": "exp_low_list_has_not",
            "desc": "Return the contents of the tag pair when the value is absent from the list. It accepts the same value, items and sep parameters as Has; supply a non-empty value.",
            "sections": [
                {
                    "type": "examples",
                    "title": "Example",
                    "items": [
                        {
                            "item": "Value absent from a list",
                            "desc": "The enclosed message is returned because the list does not contain spork.",
                            "examples": [
                                {
                                    "tag_example": "{exp:low_list:has_not value=\"spork\" items=\"knife|fork|spoon\"}\n    We don’t have a spork!\n{/exp:low_list:has_not}"
                                }
                            ]
                        }
                    ]
                }
            ]
        }
    ]
};
