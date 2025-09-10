import logging
import sys

class LoggerManager:
    """
    Centralized logging setup: console + file logging.
    """

    def __init__(self, log_file="/var/log/cashcue/app.log", level="INFO"):
        self.logger = logging.getLogger("cashcue")
        self.logger.setLevel(getattr(logging, level.upper(), logging.INFO))

        # Console handler
        ch = logging.StreamHandler(sys.stdout)
        ch.setLevel(logging.INFO)
        ch.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(message)s"))
        self.logger.addHandler(ch)

        # File handler
        fh = logging.FileHandler(log_file)
        fh.setLevel(getattr(logging, level.upper(), logging.INFO))
        fh.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(message)s"))
        self.logger.addHandler(fh)

    def get_logger(self):
        return self.logger
