from decimal import Decimal, InvalidOperation

def parse_decimal(value_str, default=0.0):
    """
    Safely parse a string to a decimal, replacing comma by dot.
    """
    if not value_str:
        return Decimal(default)
    try:
        value = Decimal(value_str.replace(",", ".").strip())
        return value
    except (InvalidOperation, AttributeError):
        return Decimal(default)
