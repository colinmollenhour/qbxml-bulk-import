This syncs a batch of QBXML (e.g. from QuickBooks Online) files via QBWC (QuickBooks Web Connector).

Usage:

1. Clone the repo on a web server (Hint: works with PHP 5.4 built-in-webserver on Windows)
2. Use the awk script to split your file into chunks if it is very large (awk -f ../split-qbxml.awk ../export_company.qbxml)
3. Create a file config.txt with the contents "{jobname}|{path}|{start}|{end}". E.g.:
     MyCompany|C:\Users\Administrator\Downloads\MyCompany\Part*.xml|1|531
4. Visit the script with '?qwc' as the query string to download the qbserv.qwc file.
5. Use the qbserv.qwc file to add the application to QuickBooks Web Connector.
6. Enter anything as the password.
7. Update the "QuickBooks XML Importer" application.
