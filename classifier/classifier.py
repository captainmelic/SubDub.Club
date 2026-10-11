import torch
from torchvision import models, transforms
from PIL import Image

# Load pretrained MobileNetV2 (CPU-only)
model = models.mobilenet_v2(weights=models.MobileNet_V2_Weights.DEFAULT)
model.eval()

# Load ImageNet labels
with open("/var/www/subtracker/classifier/imagenet_classes.txt") as f:
    idx_to_label = [line.strip() for line in f.readlines()]

# Transform for MobileNetV2
transform = transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
])

# Keywords that indicate a sub sandwich
SUB_KEYWORDS = {
    "submarine sandwich",
    "sandwich",
    "hoagie",
    "baguette",
    "french loaf",
    "hotdog",
    "roll",
    "burrito",
    "wrap",
}

def is_sub_sandwich(image_path):
    img = Image.open(image_path).convert("RGB")
    x = transform(img).unsqueeze(0)

    with torch.no_grad():
        logits = model(x)
        probs = torch.nn.functional.softmax(logits, dim=1)
        top5 = torch.topk(probs, 5)

    labels = [idx_to_label[idx] for idx in top5.indices[0]]

    for label in labels:
        if any(keyword in label.lower() for keyword in SUB_KEYWORDS):
            return True

    return False

