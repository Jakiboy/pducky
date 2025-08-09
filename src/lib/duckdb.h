// Simplified DuckDB header for PHP FFI

typedef void* duckdb_database;
typedef void* duckdb_connection;
typedef void* duckdb_prepared_statement;
typedef void* duckdb_appender;
typedef void* duckdb_config;

typedef enum {
    DuckDBSuccess = 0,
    DuckDBError = 1,
} duckdb_state;

typedef struct {
    void *data;
    bool *nullmask;
    int type;
    char *name;
} duckdb_column;

typedef struct {
    int column_count;
    int row_count;
    duckdb_column *columns;
    char *error_message;
} duckdb_result;

typedef enum {
    DUCKDB_TYPE_INVALID = 0,
    DUCKDB_TYPE_BOOLEAN,
    DUCKDB_TYPE_TINYINT,
    DUCKDB_TYPE_SMALLINT,
    DUCKDB_TYPE_INTEGER,
    DUCKDB_TYPE_BIGINT,
    DUCKDB_TYPE_UTINYINT,
    DUCKDB_TYPE_USMALLINT,
    DUCKDB_TYPE_UINTEGER,
    DUCKDB_TYPE_UBIGINT,
    DUCKDB_TYPE_FLOAT,
    DUCKDB_TYPE_DOUBLE,
    DUCKDB_TYPE_TIMESTAMP,
    DUCKDB_TYPE_DATE,
    DUCKDB_TYPE_TIME,
    DUCKDB_TYPE_INTERVAL,
    DUCKDB_TYPE_HUGEINT,
    DUCKDB_TYPE_VARCHAR,
    DUCKDB_TYPE_BLOB,
    DUCKDB_TYPE_DECIMAL,
    DUCKDB_TYPE_TIMESTAMP_S,
    DUCKDB_TYPE_TIMESTAMP_MS,
    DUCKDB_TYPE_TIMESTAMP_NS,
    DUCKDB_TYPE_ENUM,
    DUCKDB_TYPE_LIST,
    DUCKDB_TYPE_STRUCT,
    DUCKDB_TYPE_MAP,
    DUCKDB_TYPE_UUID,
    DUCKDB_TYPE_UNION,
    DUCKDB_TYPE_BIT,
} duckdb_type;

// Core database functions
duckdb_state duckdb_open(const char *path, duckdb_database *out_database);
duckdb_state duckdb_open_ext(const char *path, duckdb_database *out_database, duckdb_config config, char **out_error);
void duckdb_close(duckdb_database *database);

// Connection functions
duckdb_state duckdb_connect(duckdb_database database, duckdb_connection *out_connection);
void duckdb_disconnect(duckdb_connection *connection);

// Query functions
duckdb_state duckdb_query(duckdb_connection connection, const char *query, duckdb_result *out_result);
void duckdb_destroy_result(duckdb_result *result);

// Result functions
const char *duckdb_column_name(duckdb_result *result, int col);
duckdb_type duckdb_column_type(duckdb_result *result, int col);
int duckdb_column_count(duckdb_result *result);
int duckdb_row_count(duckdb_result *result);

// Value functions
char *duckdb_value_varchar(duckdb_result *result, int col, int row);
bool duckdb_value_boolean(duckdb_result *result, int col, int row);
char duckdb_value_int8(duckdb_result *result, int col, int row);
short duckdb_value_int16(duckdb_result *result, int col, int row);
int duckdb_value_int32(duckdb_result *result, int col, int row);
long long duckdb_value_int64(duckdb_result *result, int col, int row);
float duckdb_value_float(duckdb_result *result, int col, int row);
double duckdb_value_double(duckdb_result *result, int col, int row);

// Memory management
void duckdb_free(void *ptr);
