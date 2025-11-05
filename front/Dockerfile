# Dockerfile pour React
FROM node:18

WORKDIR /app/front

# Copier les fichiers package.json et package-lock.json pour installer les dépendances
COPY package*.json ./

# Installer les dépendances
RUN npm install

# Copier le reste de l'application
COPY . .

EXPOSE 3000

CMD npm run dev
