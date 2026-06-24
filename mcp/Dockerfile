FROM python:3.12-slim

RUN adduser --disabled-password --gecos '' --uid 1001 appuser

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY server.py AGENT.md CLAUDE.md ./
RUN chown -R appuser:appuser /app

USER appuser

ENV KANBAN_URL=http://localhost
ENV KANBAN_AGENT=claude

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD python -c "import httpx, os; httpx.get(os.environ.get('KANBAN_URL','http://localhost') + '/api.php', timeout=5)" || exit 1

CMD ["python", "server.py"]
