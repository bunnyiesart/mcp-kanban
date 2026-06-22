FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY server.py .

ENV KANBAN_URL=http://localhost
ENV KANBAN_AGENT=claude

CMD ["python", "server.py"]
