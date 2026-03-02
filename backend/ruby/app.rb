require 'bundler/setup'
require 'sinatra'
require 'json'
require 'dotenv'

require_relative 'lib/telemetry_handler'
require_relative 'lib/log_handler'

Dotenv.load(File.expand_path('../../.env', __dir__))

set :port, 8000
set :bind, '0.0.0.0'

LATITUDE_API_KEY        = ENV.fetch('LATITUDE_API_KEY')
LATITUDE_PROJECT_ID     = ENV.fetch('LATITUDE_PROJECT_ID').to_i
LATITUDE_PROMPT_PATH    = ENV.fetch('LATITUDE_PROMPT_PATH')
LATITUDE_VERSION_UUID   = ENV.fetch('LATITUDE_PROMPT_VERSION_UUID', 'live')
OPENAI_API_KEY          = ENV.fetch('OPENAI_API_KEY')

before do
  headers \
    'Access-Control-Allow-Origin'  => 'http://localhost:5173',
    'Access-Control-Allow-Headers' => 'Content-Type',
    'Access-Control-Allow-Methods' => 'POST, OPTIONS'
end

options '*' do
  200
end

post '/generate-wikipedia-article' do
  body = JSON.parse(request.body.read)
  input = body['input'] || ''

  content_type 'text/plain; charset=utf-8'
  headers \
    'Cache-Control'       => 'no-cache',
    'Connection'          => 'keep-alive',
    'X-Accel-Buffering'   => 'no'

  handler_args = {
    input: input,
    latitude_api_key: LATITUDE_API_KEY,
    latitude_project_id: LATITUDE_PROJECT_ID,
    latitude_prompt_path: LATITUDE_PROMPT_PATH,
    latitude_version_uuid: LATITUDE_VERSION_UUID,
    openai_api_key: OPENAI_API_KEY
  }

  stream do |out|
    if ENV['USE_LATITUDE_LOG_API'] == 'true'
      LogHandler.handle(**handler_args) { |delta| out << delta }
    else
      TelemetryHandler.handle(**handler_args) { |delta| out << delta }
    end
  end
end
