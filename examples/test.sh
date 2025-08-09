#!/bin/bash
G='\033[0;32m'
W='\033[0m'
Y='\033[1;33m'
B='\033[1;34m'

# Function to run command with timing
benchmark() {
    local description=$1
    local command=$2
    
    echo -e "${G}$description"
    echo -e "${B}Starting execution...${W}"
    
    start_time=$(date +%s%N)
    eval $command
    exit_code=$?
    
    end_time=$(date +%s%N)
    
    # Calculate execution time in milliseconds using shell arithmetic
    execution_time_ns=$((end_time - start_time))
    execution_time_ms=$((execution_time_ns / 1000000))
    execution_time_s=$((execution_time_ms / 1000))
    execution_time_ms_remainder=$((execution_time_ms % 1000))
    
    echo ""
    if [ $execution_time_s -gt 0 ]; then
        echo -e "${Y}⏱️ Execution time: ${execution_time_s}.$(printf "%03d" $execution_time_ms_remainder)s${W}"
    else
        echo -e "${Y}⏱️ Execution time: ${execution_time_ms}ms${W}"
    fi
    echo -e "${W}"
    echo "------------------------"
}

echo -e "${B}🚀 Pducky Benchmark"
echo -e "${W}"
echo "------------------------"

benchmark "Fetch value:" "php test-fetch-value.php"
benchmark "Fetch rows:" "php test-fetch-rows.php"
benchmark "Create database:" "php test-create-database.php"
benchmark "Loader query (FFI):" "php test-loader-query.php"

echo -e "${B}🏁 All tests completed!"
echo -e "${W}"
read -p "Press Enter to continue..."