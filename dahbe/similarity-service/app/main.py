from io import BytesIO

import torch
from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image
from transformers import CLIPModel, CLIPProcessor

MODEL_NAME = "openai/clip-vit-base-patch32"
app = FastAPI(title="Dahbe Image Embedding Service")
processor = CLIPProcessor.from_pretrained(MODEL_NAME)
model = CLIPModel.from_pretrained(MODEL_NAME)
model.eval()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/embeddings")
async def embedding(image: UploadFile = File(...)) -> dict[str, list[float]]:
    try:
        image_bytes = await image.read()
        image_data = Image.open(BytesIO(image_bytes)).convert("RGB")
        inputs = processor(images=image_data, return_tensors="pt")

        with torch.inference_mode():
            vector = model.get_image_features(**inputs)
            vector = vector / vector.norm(p=2, dim=-1, keepdim=True)

        return {"embedding": vector[0].tolist()}
    except Exception as error:
        raise HTTPException(status_code=422, detail="The image could not be embedded.") from error