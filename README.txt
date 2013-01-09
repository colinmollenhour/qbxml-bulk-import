This syncs a batch of QBXML (e.g. from QuickBooks Online) files via QBWC (QuickBooks Web Connector).

Usage:

1. Use the awk script to split your file into chunks if it is very large (awk -f ../split-qbxml.awk ../export_company.qbxml)
2. Clone the repo to a web server (Hint: works with PHP 5.4 built-in-webserver on Windows)
3. Create a file config.txt in the same directory as server.php with the contents "{jobname}|{path}|{start}|{end}". E.g.:
     MyCompany|C:\Users\Administrator\Downloads\MyCompany\Part*.xml|1|531
4. Confirm everything is ready by visiting the script url: http://your-domain-name-here/path-here/server.php?test
5. Download the qbserv.qwc file using the link from step 4 and use it to add the application to QuickBooks Web Connector.
6. Enter anything as the password.
7. Update the "QuickBooks XML Importer" application.
