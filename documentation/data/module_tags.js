window.doc_page = {
    addon: 'Low List',
    title: 'Tags',
    sections: [
        {
            title: '',
            type: 'tagtoc',
            desc: 'Low List has the following front-end tags: ',
        },
        {
            title: '',
            type: 'tags',
            desc: ''
        },
    ],
    tags: [
        {
            tag: '{exp:low_list:each}',
            shortname: 'exp_low_list_each',
            summary: "",
            desc: "Use the Each tag to loop through a list to character-separated items.",
            sections: [
                {
                    type: 'params',
                    title: 'Tag Parameters',
                    desc: '',
                    items: [
                        {
                            item: 'items',
                            desc: 'the items you want to loop through, separated by the character given in the sep parameter. Use a colon : to separate keys and values.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:each items="north|east|south|west" as="direction"}
                                        You can go {direction}.
                                    {/exp:low_list:each}                
                                    `,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'as',
                            desc: 'variables to use in the template. Defaults to key:val.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:each items="asc:Ascending|desc:Descending" as="value:label"}
                                    <label>
                                        <input type="radio" name="sort" value="{value}">
                                        {label}
                                    </label>
                                    {exp:low_list:each}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'sep',
                            desc: 'character that separates each item. Defaults to |.  The example shows Using key/value pairs and a custom separator:',
                            type: '',
                            accepts: '',
                            default: '|',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    <select name="orderby_sort">
                                    {exp:low_list:each items=":Relevance;date|asc:Publish date;title|asc:Title" sep=";"}
                                        <option value="{key}">{val}</option>
                                    {/exp:low_list:each}
                                    </select>`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'range',
                            desc: 'set to yes to treat the items as the start and end values of a range.',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:each items="99|0" range="yes"}
                                        {val} bottle{if val != 1}s{/if} of beer on the wall.
                                    {/exp:low_list:each}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        
                      
                    ]
                }
            ]
        },
        {
            tag: '{exp:low_list:has}',
            shortname: 'exp:low_list:has',
            summary: "",
            desc: "",
            sections: [
                {
                    type: 'params',
                    title: 'Tag Parameters',
                    desc: '',
                    items: [
                        {
                            item: 'value',
                            desc: 'the value to search for (needle).',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:has value="spork" items="knife|fork|spoon|spork"}
                                        We have a spork!
                                    {/exp:low_list:has}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'items',
                            desc: 'the items you want to search through, separated by the character given in the sep parameter (haystack).',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:channel:categories style="linear"}
                                        <option value="{category_id}"{exp:low_list:has value="{category_id}" items="1|2|3"} selected{/exp:low_list:has}>
                                            {category_name}
                                        </option>
                                    {/exp:channel:categories}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        {
                            item: 'sep',
                            desc: 'character that separates each item. Defaults to |.',
                            type: '',
                            accepts: '',
                            default: '|',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:has value="spork" sep="," items="knife,fork,spoon,spork"}
                                        We have a spork!
                                    {/exp:low_list:has}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        
                      
                    ]
                }
            ]
        },
        {
            tag: '{exp:low_list:has_not}',
            shortname: 'exp_low_list_has_not',
            summary: "",
            desc: "Use the Has Not tag to see if a given value is not present in a given charachter-separated list. Works identically to the Has tag:",
            sections: [
                {
                    type: 'params',
                    title: 'Tag Parameters',
                    desc: '',
                    items: [
                        {
                            item: 'Example Useage See has for all params',
                            desc: '',
                            type: '',
                            accepts: '',
                            default: '',
                            required: false,
                            added: '',
                            examples: [
                                {
                                    tag_example: `
                                    {exp:low_list:has_not value="spork" items="knife|fork|spoon"}
                                        We don't have a spork!
                                    {/exp:low_list:has_not}`,
                                    outputs: ``
                                 }
                             ]
                        },
                        
                      
                    ]
                }
            ]
        },
        


    ]
};