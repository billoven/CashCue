# cashcue_core/services.py
from cashcue_core.repositories import InstrumentRepository

class InstrumentService:
    def __init__(self, repo: InstrumentRepository):
        self.repo = repo

    def list_instruments(self):
        return self.repo.get_all()

    def add_instrument(self, symbol: str, label: str, type_: str = "STOCK", currency: str = "EUR"):
        return self.repo.create(symbol, label, type_, currency)
