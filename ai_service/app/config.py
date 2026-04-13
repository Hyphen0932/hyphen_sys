from functools import lru_cache

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = Field(default="Hyphen AI Service", validation_alias="AI_APP_NAME")
    app_env: str = Field(default="development", validation_alias="APP_ENV")
    ollama_base_url: str = Field(default="http://host.docker.internal:11434", validation_alias="OLLAMA_BASE_URL")
    ollama_model: str = Field(default="qwen2.5-coder:7b", validation_alias="OLLAMA_MODEL")
    ai_allowed_tables_raw: str = Field(
        default="hy_users,hy_user_menu,hy_user_pages,hy_user_permissions",
        validation_alias="AI_ALLOWED_TABLES",
    )
    ai_result_row_limit: int = Field(default=50, validation_alias="AI_RESULT_ROW_LIMIT")
    ai_memory_window: int = Field(default=6, validation_alias="AI_MEMORY_WINDOW")

    db_host: str = Field(default="db", validation_alias="AI_DB_HOST")
    db_port: int = Field(default=3306, validation_alias="AI_DB_PORT")
    db_name: str = Field(default="hyphen_sys", validation_alias="AI_DB_NAME")
    db_user: str = Field(default="hyphen_user", validation_alias="AI_DB_USER")
    db_password: str = Field(default="hyphen_pass", validation_alias="AI_DB_PASSWORD")

    model_config = SettingsConfigDict(env_file=".env", extra="ignore", case_sensitive=False)

    @property
    def allowed_tables(self) -> list[str]:
        return [item.strip() for item in self.ai_allowed_tables_raw.split(",") if item.strip()]

    @property
    def database_url(self) -> str:
        return (
            f"mysql+pymysql://{self.db_user}:{self.db_password}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}?charset=utf8mb4"
        )


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
