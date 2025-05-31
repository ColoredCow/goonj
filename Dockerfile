# Use an official WordPress image as the base
FROM wordpress:latest

# Ensure that the /var/www/html directory exists
RUN mkdir -p /var/www/html

# Install SSH client
RUN apt-get update && apt-get install -y openssh-client

# Ensure /root/.ssh directory exists and set permissions
RUN mkdir -p /root/.ssh && chmod 600 /root/.ssh

# Copy your existing WordPress code, themes, and plugins to the container
COPY ./wp-content /var/www/html/wp-content
