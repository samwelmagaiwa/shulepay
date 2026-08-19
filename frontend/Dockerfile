# Stage 1: Build Vue app
FROM node:20-alpine AS builder

WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Serve with Nginx
FROM nginx:alpine

# Install envsubst (included in nginx:alpine via gettext)
RUN apk add --no-cache gettext

# Copy built assets
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx template
COPY nginx.conf /etc/nginx/templates/default.conf.template

# Entrypoint: substitute env vars then start nginx
CMD ["/bin/sh", "-c", "envsubst '${FRONTEND_SERVER_NAME} ${BACKEND_UPSTREAM}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'"]
