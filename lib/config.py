import os
import sys

class ConfigManager:
    """
    Load and manage dotenv-style configuration.
    Supports typed getters and default values.
    """

    def __init__(self):
        config_file = os.environ.get("CASHCUE_CONFIG_FILE")
        if not config_file:
            print("[ERROR] Environment variable CASHCUE_CONFIG_FILE is not set.")
            sys.exit(1)
        if not os.path.exists(config_file):
            print(f"[ERROR] Config file not found: {config_file}")
            sys.exit(1)
        self.config = {}
        self.load(config_file)

    def load(self, filepath):
        with open(filepath, "r") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if "=" in line:
                    key, value = line.split("=", 1)
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
