# MS-CSV-Converter
Converts a CSV file of data into a formatted HTML table for you WordPress site

**== Installation ==**

This section describes how to install the plugin and get it working.

1. Upload the MS-CSV-Converter repo to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Upload the csv file you want to convert to the server e.g use the format from the example template
4. Enter the following shortcode to the page you want the HTML table of converted data to appear:

`[csv_converter class="{class_name}" url="{url}" header="true" group="true"]`

Attributes:

`class:  Adds a class to the table element.`
`url:    The https file path of where the .csv file is located.`
`header: If true if formats the titles of the table, otherwise they will add same format as theother data elements.`
`group:  If true it will format the group elements in the table.`

You should now be all set!
