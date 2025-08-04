#!/bin/sh

USER_DIR="/var/www/user"
CHECK_FILES="
/config/config.yaml
/config/applications.yaml
/config/framework/security.yaml
/translations/messages.en.yaml
"

# You can override this argument in the environment
# e.g.: docker -e INCLUDE_EXAMPLES="true"
if [ $INCLUDE_EXAMPLES = "true" ]; then
  CHECK_FILES="$CHECK_FILES
  /config/applications/books.yaml
  /config/applications/authors.yaml
  /config/applications/recipes.yaml
  /translations/app_recipes.en.yaml
  "
fi

for FILE in $CHECK_FILES; do
  if [ ! -f "$USER_DIR$FILE" ]; then
    if [ -f "$USER_DIR$FILE.dist" ]; then
      echo "Creating missing file: $USER_DIR$FILE"
      cp "$USER_DIR$FILE.dist" "$USER_DIR$FILE"
    else
      echo "Warning: $USER_DIR$FILE.dist not found, skipping"
    fi
  fi
done

exec "$@"
