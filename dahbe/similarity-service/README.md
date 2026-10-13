# Image Embedding Service

This service generates normalized image embeddings using the pretrained
`openai/clip-vit-base-patch32` model. Laravel sends each product image to
`POST /embeddings` and stores the returned vector in `product_images.embedding`.

Run it with:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Configure Laravel with:

```env
IMAGE_EMBEDDING_SERVICE_URL=http://127.0.0.1:8001
IMAGE_EMBEDDING_SERVICE_TIMEOUT=60
```