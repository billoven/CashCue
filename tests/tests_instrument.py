import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_create_and_list_instrument():
    # Create new instrument
    response = client.post("/instruments", params={"symbol": "AAPL", "label": "Apple Inc."})
    assert response.status_code == 200
    data = response.json()
    assert data["symbol"] == "AAPL"

    # List instruments
    response = client.get("/instruments")
    assert response.status_code == 200
    instruments = response.json()
    assert any(instr["symbol"] == "AAPL" for instr in instruments)
