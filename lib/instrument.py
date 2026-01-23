class Instrument:
    """
    Represents a financial instrument (stock, ETF, bond, etc.)
    """

    def __init__(self, instrument_id, symbol, label, type_="STOCK", currency="EUR"):
        self.id = instrument_id
        self.symbol = symbol
        self.label = label
        self.type = type_
        self.currency = currency

class BrokerAccount:
    """
    Represents a broker account.
    """

    def __init__(self, broker_account_id, name, account_number, account_type, currency="EUR"):
        self.id = broker_account_id
        self.name = name
        self.account_number = account_number
        self.account_type = account_type
        self.currency = currency
