#!/bin/bash
G='\033[0;32m'
W='\033[0m'

echo -e "${G}Fetch value: "
php fetch-value.php
echo -e "${W}"
echo "------------------------"
echo -e "${G}Fetch rows: "
php fetch-rows.php
echo -e "${W}"
echo "------------------------"
echo -e "${G}Create database: "
php create-database.php
echo -e "${W}"
echo "Done!"
echo "------------------------"
echo -e "${G}Loader query (FFI): "
php loader-query.php
echo -e "${W}"
echo "------------------------"
read -p "Press Enter to continue..."