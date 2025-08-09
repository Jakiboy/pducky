#!/bin/bash
# Generate ~1M rows of dummy product data in data.csv, then compress to data.csv.gz

OUT="data.csv"
GZ="data.csv.gz"
ROWS=1000000
BATCH_SIZE=10000

echo "Generating $ROWS rows of data..."

# Write header
echo "name,price,image,ean,url" > "$OUT"

# Generate data in batches for much faster performance
for batch in $(seq 0 $((ROWS/BATCH_SIZE - 1))); do
	start=$((batch * BATCH_SIZE + 1))
	end=$(((batch + 1) * BATCH_SIZE))
	
	# Generate batch using awk for speed
	awk -v start=$start -v end=$end 'BEGIN {
		srand()
		for (i = start; i <= end; i++) {
			price = sprintf("%.2f", 10 + rand() * 989)
			ean = 1000000000000 + i
			printf "\"Product %d\",%s,\"https://via.placeholder.com/300x300/00AAFF/FFFFFF?text=Product+%d\",\"%d\",\"https://example.com/products/product-%d\"\n", i, price, i, ean, i
		}
	}' >> "$OUT"
	
	echo "Generated batch $((batch + 1))/$(((ROWS/BATCH_SIZE)))"
done

echo "Compressing to $GZ..."
gzip -c "$OUT" > "$GZ"

# Show file sizes
echo "Generated files:"
ls -lh "$OUT" "$GZ"
