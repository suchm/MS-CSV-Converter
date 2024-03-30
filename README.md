# MS-CSV-Converter
WordPress plugin that converts a CSV file of data into a formatted HTML table.

**== Installation ==**

This section describes how to install the plugin and get it working.

1. Upload the MS-CSV-Converter repo to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Upload the csv file you want to convert to the server e.g use the format from the example template.
4. Add the following shortcode to the page the HTML table of converted data should appear on:

`[csv_converter class="{class_name}" url="{url}" header="true" group="true"]`

Attributes:
```
class:  Adds a class to the table element.
url:    The https file path of where the .csv file is located.
header: If true the first line of data is formatted as titles, otherwise the standard formatting is used.
group:  If true the second line of data is formatted as group elements, otherwise the standard formatting is used.
```
5. Once the HTML table has initially loaded you can make further updates by either running the WP_CLI command `wp csv_update` or setting up a cron job to run the command automatically. The WP_CLI command checks if a new .csv file has been uploaded to the same file path and if so reloads the HTML table. If a new file path is used the WP_CLI command doesn't need to be run.

You should now be all set!
