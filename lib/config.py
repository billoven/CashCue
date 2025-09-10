import os
import sys

class ConfigManager:
    """
    Load and manage dotenv-style configuration.
    Supports typed getters and default values.
    """

    def __init__(self, filepath="/etc/cashcue/cashcue.conf"):
        self.config = {}
        self.load(filepath)

    def load(self, filepath):
        if not os.path.exists(filepath):
            print(f"[ERROR] Config file not found: {filepath}")
            sys.exit(1)
        with open(filepath, "r") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if "=" in line:
                    key, value = line.split("=", 1)
                    # Remove inline comment
                    value = value.split("#", 1)[0].strip()
                    self.config[key.strip()] = value

    def get(self, key, default=None):
        return self.config.get(key, default)

    def get_int(self, key, default=0):
        try:
            return int(self.config.get(key, default))
        except ValueError:
            return default

    def get_float(self, key, default=0.0):
        try:
            return float(self.config.get(key, default))
        except ValueError:
            return default

    def get_bool(self, key, default=False):
        val = self.config.get(key, str(default)).lower()
        return val in ("true", "1", "yes")
