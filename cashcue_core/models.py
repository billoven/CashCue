# cashcue_core/models.py
from sqlalchemy import Column, Integer, BigInteger, String, Enum, CHAR, TIMESTAMP, DECIMAL, Date, Boolean, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import relationship

Base = declarative_base()

class BrokerAccount(Base):
    __tablename__ = "broker_account"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(100), nullable=False)
    account_number = Column(String(50), unique=True)
    account_type = Column(Enum('PEA','CTO','ASSURANCE_VIE','PER','OTHER'), nullable=False, default='PEA')
    currency = Column(CHAR(3), default='EUR')
    created_at = Column(TIMESTAMP)

    # Relationships
    orders = relationship("OrderTransaction", back_populates="broker")
    dividends = relationship("Dividend", back_populates="broker")
    snapshots = relationship("PortfolioSnapshot", back_populates="broker")

class Instrument(Base):
    __tablename__ = "instrument"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    symbol = Column(String(20), unique=True, nullable=False)
    label = Column(String(255), nullable=False)
    isin = Column(String(20), unique=True)
    type = Column(Enum('STOCK','ETF','BOND','FUND','OTHER'), default='STOCK')
    currency = Column(CHAR(3), default='EUR')
    created_at = Column(TIMESTAMP)

    # Relationships
    orders = relationship("OrderTransaction", back_populates="instrument")
    dividends = relationship("Dividend", back_populates="instrument")
    realtime_prices = relationship("RealtimePrice", back_populates="instrument")
    daily_prices = relationship("DailyPrice", back_populates="instrument")

class OrderTransaction(Base):
    __tablename__ = "order_transaction"

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    broker_account_id = Column(Integer, ForeignKey("broker_account.id"), nullable=False)
    instrument_id = Column(Integer, ForeignKey("instrument.id"), nullable=False)
    order_type = Column(Enum("BUY","SELL"), nullable=False)
    quantity = Column(DECIMAL(12,4), nullable=False)
    price = Column(DECIMAL(12,4), nullable=False)
    fees = Column(DECIMAL(12,4), nullable=False)
    total_cost = Column(DECIMAL(14,2))  # can be computed in app
    trade_date = Column(Date, nullable=False)
    settled = Column(Boolean, default=False)

    broker = relationship("BrokerAccount", back_populates="orders")
    instrument = relationship("Instrument", back_populates="orders")

class Dividend(Base):
    __tablename__ = "dividend"

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    broker_account_id = Column(Integer, ForeignKey("broker_account.id"), nullable=False)
    instrument_id = Column(Integer, ForeignKey("instrument.id"), nullable=False)
    amount = Column(DECIMAL(12,4), nullable=False)
    gross_amount = Column(DECIMAL(12,4))
    currency = Column(CHAR(3), default='EUR')
    payment_date = Column(Date, nullable=False)
    taxes_withheld = Column(DECIMAL(12,4), default=0.0)
    created_at = Column(TIMESTAMP)

    broker = relationship("BrokerAccount", back_populates="dividends")
    instrument = relationship("Instrument", back_populates="dividends")

class PortfolioSnapshot(Base):
    __tablename__ = "portfolio_snapshot"

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    broker_account_id = Column(Integer, ForeignKey("broker_account.id"), nullable=False)
    date = Column(Date, nullable=False)
    total_value = Column(DECIMAL(14,2), nullable=False)
    invested_amount = Column(DECIMAL(14,2), nullable=False)
    unrealized_pl = Column(DECIMAL(14,2), default=0.0)
    realized_pl = Column(DECIMAL(14,2), default=0.0)
    dividends_received = Column(DECIMAL(14,2), default=0.0)
    cash_balance = Column(DECIMAL(14,2), default=0.0)

    broker = relationship("BrokerAccount", back_populates="snapshots")

class RealtimePrice(Base):
    __tablename__ = "realtime_price"

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    instrument_id = Column(Integer, ForeignKey("instrument.id"), nullable=False)
    price = Column(DECIMAL(12,4), nullable=False)
    currency = Column(CHAR(3), default='EUR')
    captured_at = Column(TIMESTAMP)
    capital_exchanged_percent = Column(DECIMAL(5,2))

    instrument = relationship("Instrument", back_populates="realtime_prices")

class DailyPrice(Base):
    __tablename__ = "daily_price"

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    instrument_id = Column(Integer, ForeignKey("instrument.id"), nullable=False)
    date = Column(Date, nullable=False)
    open_price = Column(DECIMAL(12,4))
    high_price = Column(DECIMAL(12,4))
    low_price = Column(DECIMAL(12,4))
    close_price = Column(DECIMAL(12,4))
    volume = Column(BigInteger)
    pct_change = Column(DECIMAL(6,2))

    instrument = relationship("Instrument", back_populates="daily_prices")
