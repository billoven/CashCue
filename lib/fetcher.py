import re
import time
import requests
import logging

class PriceFetcher:
    """
    Fetch stock/ETF prices and related information from external sources.
    """

    def __init__(self, url_pattern, retries=3, timeout=10):
        self.url_pattern = url_pattern
        self.retries = retries
        self.timeout = timeout

    def fetch_price(self, symbol):
        url = self.url_pattern.format(category="trackers", symbol=symbol)
        for attempt in range(self.retries):
            try:
                response = requests.get(url, timeout=self.timeout)
                if response.status_code == 200:
                    html_content = response.text
                    # Extract price
                    match = re.search(
                        r'<span class="c-instrument c-instrument--last" data-ist-last>(\d+[.,]\d+)</span>',
                        html_content
                    )
                    if match:
                        return float(match.group(1).replace(",", ".").strip())
                    else:
                        logging.warning(f"No price found for {symbol}")
                        return None
                else:
                    logging.warning(f"HTTP status {response.status_code} for {symbol}")
            except requests.RequestException as e:
                logging.warning(f"Attempt {attempt+1}/{self.retries} failed for {symbol}: {e}")
            time.sleep(1)
        logging.error(f"Failed to fetch price after {self.retries} attempts: {symbol}")
        return None
