FROM nginx:1.21

COPY ./docker/vhost.conf /etc/nginx/conf.d/default.conf
COPY ./ssl/sail-selfsigned.crt /etc/nginx/ssl/nginx.crt
COPY ./ssl/sail-selfsigned.key /etc/nginx/ssl/nginx.key

RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log