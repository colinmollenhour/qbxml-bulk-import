This syncs a batch of QBXML (e.g. from QuickBooks Online) files via QBWC (QuickBooks Web Connector).

Usage:

1. Clone the repo on a web server.
2. Use the awk script to split your file into chunks if it is very large.
3. Create a file config.txt with the contents "{path}|{start}|{end}". E.g.:
     /var/Part*.xml|1|531
4. Visit the script with '?qwc' as the query string to download the qbserv.qwc file
5. Use the qbserv.qwc file to add the application to QuickBooks Web Connector.
6. Enter anything as the password.
7. Run the application!
