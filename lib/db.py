import pymysql
import logging

class DatabaseConnection:
    """
    Handles database connections and provides safe query execution.
    """

    def __init__(self, host, user, password, database, port=3306):
        self.host = host
        self.user = user
        self.password = password
        self.database = database
        self.port = port
        self.conn = None

    def connect(self):
        try:
            self.conn = pymysql.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                port=self.port,
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
        except pymysql.MySQLError as e:
            logging.error(f"DB connection failed: {e}")
            raise

    def cursor(self):
        if not self.conn:
            self.connect()
        return self.conn.cursor()

    def execute(self, query, params=None):
        with self.cursor() as cur:
            cur.execute(query, params or ())
            return cur

    def close(self):
        if self.conn:
            self.conn.close()
            self.conn = None
