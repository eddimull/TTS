const path = require('path');
const fs = require('fs');

module.exports = {
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
        },
        devServer:{

            https:{
                key: fs.readFileSync('./ssl/privkey.pem'),
                cert: fs.readFileSync('./ssl/fullchain.pem'),
            }
        }
    },
};
