# cashcue_core/repositories.py
from cashcue_core.models import Instrument

class InstrumentRepository:
    def __init__(self, db):
        self.db = db

    def get_all(self):
        return self.db.query(Instrument).all()

    def get_by_symbol(self, symbol: str):
        return self.db.query(Instrument).filter(Instrument.symbol == symbol).first()

    def create(self, symbol: str, label: str, type_: str = "STOCK", currency: str = "EUR"):
        instr = Instrument(symbol=symbol, label=label, type=type_, currency=currency)
        self.db.add(instr)
        self.db.commit()
        self.db.refresh(instr)
        return instr
