#
# This gawk script splits a QBXML file into smaller "Parts"
#
# Usage:
#   $ gawk -f split-qbxml.gawk < my.xml
# Or:
#   $ gawk -f split-qbxml.gawk -- -n=100 -f=Part-%d.xml < my.xml
#
BEGIN {
  part = 0
  size = 1000
  format = "Part-%d.xml"
  for (i = 1; i < ARGC; i++) {
    if (ARGV[i] ~ /^-n=/) {
      size=ARGV[i]
      sub(/^-n=/,"",size)
    } else if (ARGV[i] ~ /^-f=/) {
      format=ARGV[i]
      sub(/^-f=/,"",format)
    } else break
    delete ARGV[i]
  }
}
/<[[:alnum:]]+Rq requestID=/ {
  count = 0
  rfile=sprintf(format, part+1)
  print > rfile
  while (count < size) {
    if ( ! getline || $0 ~ /<\/QBXMLMsgsRq>/ )
      break
    print > rfile
    if ($0 ~ /<\/[[:alnum:]]+Rq>/ )
      count++
  }
  close(rfile)
  part++
}
END {
  printf("Split input into %d files.\n", part)
}
