# Low List for ExpressionEngine

Low List lets you loop and search through a list of character-separated items. Requires **EE 3.0.0+** and is especially useful in combination with [Low Search](http://gotolow/addons/low-search).

## Installation

- [Download](https://github.com/low/low_list/archive/master.zip) and unzip;
- Copy the `low_list` folder to your `system/user/addons` directory;
- In your Control Panel, go to the Add-on Manager and install Low List;
- All set!

## Tags

### Each tag

Use the Each tag to loop through a list to character-separated items.

#### Parameters

- `items=`: the items you want to loop through, separated by the character given in the `sep` parameter. Use a colon `:` to separate keys and values.
- `as=`: variables to use in the template. Defaults to `key:val`.
- `sep=`: character that separates each item. Defaults to `|`.
- `range=`: set to `yes` to treat the items as the start and end values of a range.

#### Examples

    {exp:low_list:each items="north|east|south|west" as="direction"}
        You can go {direction}.
    {/exp:low_list:each}

Using key/value pairs and custom variable names:

    {exp:low_list:each items="asc:Ascending|desc:Descending" as="value:label"}
		<label>
		    <input type="radio" name="sort" value="{value}">
		    {label}
		</label>
    {exp:low_list:each}

Using key/value pairs and a custom separator:

	<select name="orderby_sort">
		{exp:low_list:each items=":Relevance;date|asc:Publish date;title|asc:Title" sep=";"}
			<option value="{key}">{val}</option>
		{/exp:low_list:each}
	</select>

Using the items parameter to define a numerical range:

	{exp:low_list:each items="99|0" range="yes"}
		{val} bottle{if val != 1}s{/if} of beer on the wall.
	{/exp:low_list:each}

### Has tag

Use the Has tag to see if a given value is present in a given character-separated list. When the value is present, the tagdata is returned. Otherwise, an empty string is returned.

#### Parameters

- `value=`: the value to search for (needle).
- `items=`: the items you want to search through, separated by the character given in the `sep` parameter (haystack).
- `sep=`: character that separates each item. Defaults to `|`.

#### Examples

    {exp:low_list:has value="spork" items="knife|fork|spoon"}
        We have a spork!
    {/exp:low_list:has}

Setting a `checked` or `selected` attribute:

	{exp:channel:categories style="linear"}
		<option value="{category_id}"{exp:low_list:has value="{category_id}" items="1|2|3"} selected{/exp:low_list:has}>
			{category_name}
		</option>
	{/exp:channel:categories}

### Has Not tag

Use the Has Not tag to see if a given value is *not* present in a given charachter-separated list. Works identically to the Has tag:

    {exp:low_list:has_not value="spork" items="knife|fork|spoon"}
        We don't have a spork!
    {/exp:low_list:has_not}

## Mad Props

Thanks to [Loopee](https://github.com/danott/ee-loopee) for the inspiration.