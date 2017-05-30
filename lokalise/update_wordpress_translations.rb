#!/usr/bin/env ruby

require 'timeout'
require 'json'
require 'pp'

if ARGV.length != 1 && ENV['LOKALISE_TOKEN'] == nil
  puts "\n\nPlease provide your Lokalise token as the first argument:\n\n"
  puts "    ./update_wordpress_translations.rb YOUR-LOKALISE-TOKEN\n\n"
  puts "Alternatively you can export the token in your shell. E.g. put\n"
  puts "this line into your ~/.bashrc, ~/.zshrc, or where ever you keep"
  puts "your exported variables:\n\n"
  puts "    export LOKALISE_TOKEN=\"YOUR-LOKALISE-TOKEN\"\n\n"
  puts "Make sure you restart your shell after you added this line.\n\n"
  puts "Log into Lokalise and get your token here: https://lokalise.co/account/\n\n"
  puts "Description: This script downloads the translation files for the Website project"
  puts "from Lokalise.co, transforms the data and spits out multiple JSON files and a"
  puts "Javascript file, which is used for autocompletion in the Wordpress backend.\n\n\n"
  exit(1)
end




LOKALISE_TOKEN = ARGV[0] || ENV['LOKALISE_TOKEN']
WEBSITE_PROJECT_ID = '215970635922d0fc368e99.32143818'
LANGUAGES = %w[de en fr it]

# How many seconds will we wait for Lokalise to respond?
LOKALISE_TIMEOUT = 10




def fetch_from_lokalise
  command = "lokalise --token #{LOKALISE_TOKEN} export #{WEBSITE_PROJECT_ID} --type json"
  puts "\n\nFetching from Lokalise (waiting #{LOKALISE_TIMEOUT} seconds max)...\n\n"

  begin
    Timeout::timeout(LOKALISE_TIMEOUT) do
      puts `#{command}`
      puts "\n\n"
    end
  rescue Timeout::Error
    raise "Failed to fetch translation files from Lokalise, because it timed out.\nThe command:\n\n#{command}"
  end

  raise "Failed to fetch translation files from Lokalise." if $?.exitstatus != 0
end

def unpack_translations_files
  puts "Unpacking translation files...\n\n"
  puts `unzip -o Website.zip`
  puts "\n\n"
end

def rename_translation_files
  puts "Renaming downloaded nested files...\n\n"

  LANGUAGES.each do |language|
    puts `mv -v #{language}.json #{language}_nested.json`
  end

  puts "\n\n"
end

def recursively_flatten_dictionary(dictionary, keys, flattened)
  dictionary.each do |key, value|
    breadcrumbs = keys.clone
    breadcrumbs << key

    if value.is_a? Hash
      flattened.merge(recursively_flatten_dictionary(value, breadcrumbs, flattened))
    elsif value.is_a? String
      flattened[breadcrumbs.join('.')] = { "text" => value }
    else
      raise "Unexpected data type: #{value.class}"
    end
  end

  return flattened
end

def flatten_json_files

  puts "Flattening nested hashes from Lokalise...\n\n"

  flat_data = {}

  LANGUAGES.each do |language|

    data = JSON.parse(File.read("#{language}_nested.json"))
    flat_data[language] = recursively_flatten_dictionary(data, [], {})
  end

  return flat_data
end

def write_data_to_json_files(data)
  puts "Writing flattened data to JSON files...\n\n"

  LANGUAGES.each do |language|
    File.open("#{language}.json", 'w') do |file|
      file.write(JSON.dump(data[language]))
    end

  end
end

def write_data_autocomplete_json_file(data)
  puts "Transforming translation data for autocomplete file...\n\n"

  all_keys = data.map{ |language, translations| translations.map{ |key, value| key } }
  all_keys = all_keys.flatten.uniq

  id = 0
  transformed_data = {}
  all_keys.each do |key|
    transformed_data[id] = { "key" => key }

    LANGUAGES.each do |language|
      transformed_data[id][language] = data[language][key]['text']
    end

    id += 1
  end

  puts "Writing autocomplete file...\n\n"

  File.open('autocomplete-data.js', 'w') do |f|
    f.write('var wtimlTranslations = ')
    f.write(JSON.dump({ 'texts' => transformed_data, 'languages' => LANGUAGES }))
    f.write(';')
  end

end


fetch_from_lokalise()
unpack_translations_files()
rename_translation_files()
data = flatten_json_files()
write_data_to_json_files(data)
write_data_autocomplete_json_file(data)
