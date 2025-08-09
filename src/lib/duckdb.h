// DuckDB FFI header for PHP - Simplified for FFI compatibility
// Based on official DuckDB C API

typedef uint64_t idx_t;

//===--------------------------------------------------------------------===//
// Enums
//===--------------------------------------------------------------------===//
typedef enum DUCKDB_TYPE {
	DUCKDB_TYPE_INVALID = 0,
	DUCKDB_TYPE_BOOLEAN = 1,
	DUCKDB_TYPE_TINYINT = 2,
	DUCKDB_TYPE_SMALLINT = 3,
	DUCKDB_TYPE_INTEGER = 4,
	DUCKDB_TYPE_BIGINT = 5,
	DUCKDB_TYPE_UTINYINT = 6,
	DUCKDB_TYPE_USMALLINT = 7,
	DUCKDB_TYPE_UINTEGER = 8,
	DUCKDB_TYPE_UBIGINT = 9,
	DUCKDB_TYPE_FLOAT = 10,
	DUCKDB_TYPE_DOUBLE = 11,
	DUCKDB_TYPE_TIMESTAMP = 12,
	DUCKDB_TYPE_DATE = 13,
	DUCKDB_TYPE_TIME = 14,
	DUCKDB_TYPE_INTERVAL = 15,
	DUCKDB_TYPE_HUGEINT = 16,
	DUCKDB_TYPE_UHUGEINT = 32,
	DUCKDB_TYPE_VARCHAR = 17,
	DUCKDB_TYPE_BLOB = 18,
	DUCKDB_TYPE_DECIMAL = 19,
	DUCKDB_TYPE_TIMESTAMP_S = 20,
	DUCKDB_TYPE_TIMESTAMP_MS = 21,
	DUCKDB_TYPE_TIMESTAMP_NS = 22,
	DUCKDB_TYPE_ENUM = 23,
	DUCKDB_TYPE_LIST = 24,
	DUCKDB_TYPE_STRUCT = 25,
	DUCKDB_TYPE_MAP = 26,
	DUCKDB_TYPE_ARRAY = 33,
	DUCKDB_TYPE_UUID = 27,
	DUCKDB_TYPE_UNION = 28,
	DUCKDB_TYPE_BIT = 29,
	DUCKDB_TYPE_TIME_TZ = 30,
	DUCKDB_TYPE_TIMESTAMP_TZ = 31,
	DUCKDB_TYPE_ANY = 34,
	DUCKDB_TYPE_VARINT = 35,
	DUCKDB_TYPE_SQLNULL = 36,
	DUCKDB_TYPE_STRING_LITERAL = 37,
	DUCKDB_TYPE_INTEGER_LITERAL = 38
} duckdb_type;

typedef enum duckdb_state {
	DuckDBSuccess = 0,
	DuckDBError = 1
} duckdb_state;

//===--------------------------------------------------------------------===//
// Forward Declarations  
//===--------------------------------------------------------------------===//
typedef void* duckdb_database;
typedef void* duckdb_connection;
typedef void* duckdb_prepared_statement;
typedef void* duckdb_appender;
typedef void* duckdb_config;

// Simplified result structure - opaque to match original design
typedef struct {
    void *internal_data;
} duckdb_result;

//===--------------------------------------------------------------------===//
// Core Functions
//===--------------------------------------------------------------------===//
duckdb_state duckdb_open(const char *path, duckdb_database *out_database);
duckdb_state duckdb_open_ext(const char *path, duckdb_database *out_database, duckdb_config config, char **out_error);
void duckdb_close(duckdb_database *database);

duckdb_state duckdb_connect(duckdb_database database, duckdb_connection *out_connection);
void duckdb_disconnect(duckdb_connection *connection);

duckdb_state duckdb_query(duckdb_connection connection, const char *query, duckdb_result *out_result);
void duckdb_destroy_result(duckdb_result *result);

const char *duckdb_column_name(duckdb_result *result, idx_t col);
duckdb_type duckdb_column_type(duckdb_result *result, idx_t col);
idx_t duckdb_column_count(duckdb_result *result);
idx_t duckdb_row_count(duckdb_result *result);

char *duckdb_value_varchar(duckdb_result *result, idx_t col, idx_t row);
bool duckdb_value_boolean(duckdb_result *result, idx_t col, idx_t row);
int8_t duckdb_value_int8(duckdb_result *result, idx_t col, idx_t row);
int16_t duckdb_value_int16(duckdb_result *result, idx_t col, idx_t row);
int32_t duckdb_value_int32(duckdb_result *result, idx_t col, idx_t row);
int64_t duckdb_value_int64(duckdb_result *result, idx_t col, idx_t row);
float duckdb_value_float(duckdb_result *result, idx_t col, idx_t row);
double duckdb_value_double(duckdb_result *result, idx_t col, idx_t row);

void duckdb_free(void *ptr);
