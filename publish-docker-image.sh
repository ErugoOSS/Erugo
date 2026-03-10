#!/bin/bash

# Check if DOCKER_HUB_USERNAME is set
if [ -z "$DOCKER_HUB_USERNAME" ]; then
    echo "Error: DOCKER_HUB_USERNAME environment variable is not set"
    echo "Usage: DOCKER_HUB_USERNAME=yourusername ./build-push.sh [version]"
    exit 1
fi

# Get version from argument or use 'latest'
VERSION=${1:-latest}

# Ensure buildx is set up correctly
docker buildx create --name mybuilder --driver docker-container --bootstrap 2>/dev/null || true
docker buildx use mybuilder

# Build for multiple platforms using buildx
docker buildx build \
    --platform linux/amd64,linux/arm64 \
    --progress=plain \
    -t $DOCKER_HUB_USERNAME/erugo:$VERSION \
    -t $DOCKER_HUB_USERNAME/erugo:latest \
    -f docker/alpine/Dockerfile \
    --push \
    .