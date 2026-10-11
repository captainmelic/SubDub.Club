from flask import Flask, request
from classifier import is_sub_sandwich

app = Flask(__name__)

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/classify")
def classify():
    if "image" not in request.files:
        return {"error": "No image uploaded"}, 400

    file = request.files["image"]
    temp_path = "/tmp/upload.jpg"
    file.save(temp_path)

    result = is_sub_sandwich(temp_path)
    return {"is_sub": result}

