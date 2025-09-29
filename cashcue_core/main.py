# cashcue_core/main.py
from fastapi import FastAPI, Depends
from typing import List
from sqlalchemy.orm import Session
from cashcue_core.models import Base, Instrument
from cashcue_core.db import engine, get_db
from cashcue_core.repositories import InstrumentRepository
from cashcue_core.services import InstrumentService
from lib.config import ConfigManager  # optional if you need ConfigManager


# Create tables (dev mode)
Base.metadata.create_all(bind=engine)

app = FastAPI(title="CashCue Core API")

def get_service(db: Session = Depends(get_db)):
    repo = InstrumentRepository(db)
    return InstrumentService(repo)

@app.get("/instruments", response_model=List[str])
def list_instruments(service: InstrumentService = Depends(get_service)):
    instruments = service.list_instruments()
    return [i.symbol for i in instruments]

@app.post("/instruments")
def create_instrument(symbol: str, label: str, service: InstrumentService = Depends(get_service)):
    instr = service.add_instrument(symbol, label)
    return {"id": instr.id, "symbol": instr.symbol, "label": instr.label}
