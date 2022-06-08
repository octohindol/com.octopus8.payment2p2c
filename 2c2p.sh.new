#!/bin/sh

echo "GENERATING PRIVATE KEY...";
openssl genrsa -des3 -out private.pem 2048

if [ -f private.pem ]
then
echo "=======================\n";
echo "GENERATING NEW CSR...";
openssl req -new -key private.pem -out certfile.csr
else
echo "ERROR in GENERATING PRIVATE KEY...ABORTING";
fi;



if [ -f certfile.csr ]
then
echo "=======================\n";
echo "GENERATING SELF SIGN CERTIFICATE";
openssl x509 -req -days 3650 -in certfile.csr -signkey private.pem -out cert.crt
cat private.pem > iosprivate.pem
cat cert.crt >> iosprivate.pem
else
echo "ERROR in GENERATING SELF SIGN CERTIFICATE...ABORTING";
fi;





if [ -f cert.crt ]
then
rm certfile.csr
echo "=======================\n";
echo "FINISH. Certificate created";
echo "=======================\n";

fi;


