all:

buildimage:
	docker build -f Containerfile  -t vitexsoftware/file2sharepoint:latest .

buildx:
	docker buildx build  -f Containerfile  . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/file2sharepoint:latest

drun:
	docker run  -f Containerfile --env-file .env vitexsoftware/file2sharepoint:latest
