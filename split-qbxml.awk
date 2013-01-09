#
# This awk script splits a QBXML file into smaller "Parts"
#
# Usage - replace "node" by your tag and run: $ awk -f split-qbxml.awk my.xml
#
BEGIN { part = 1 }
/<\w+Rq requestID=/ {
  rfile="Part" part ".xml"
  print $0 > rfile
  getline
  count = 0
  while (count < 1000) {
    while ($0 !~ /<\/\w+Rq>/ ) {
      print > rfile
      getline
    }
    if ($0 ~ /<\/QBXMLMsgsRq>/ ) {
      break
    }
    count++
    print $0 > rfile
    getline
  }
  close(rfile)
  part++
}
