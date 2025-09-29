# cashcue_core/db.py
import os
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, scoped_session
from lib.config import ConfigManager  # fixed import

# Load configuration from CASHCUE_CONFIG_FILE environment variable
config = ConfigManager()

DATABASE_URL = (
    f"mysql+pymysql://{config.get('DB_USER')}:{config.get('DB_PASS')}"
    f"@{config.get('DB_HOST')}:{config.get('DB_PORT')}/{config.get('DB_NAME')}"
)

engine = create_engine(
    DATABASE_URL,
    echo=(config.get("APP_LOG_LEVEL", "INFO").upper() == "DEBUG"),
    pool_pre_ping=True,
)

SessionLocal = scoped_session(
    sessionmaker(autocommit=False, autoflush=False, bind=engine)
)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

