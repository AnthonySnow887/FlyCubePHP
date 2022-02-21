#!/bin/bash

#
# Function for test page response code
# Input args:
# 1 - host
# 2 - application URL prefix (may be empty)
#
function test_page_code() {
    local host=$1
    local url_prefix=$2
    
    #
    # get http code result
    #
    local http_code=`curl -o /dev/null --silent --head --write-out '%{http_code}\n' http://$host/$url_prefix`
    echo "$http_code"
}

#
# Function for test page load
# Input args:
# 1 - host
# 2 - application URL prefix (may be empty)
#
function test_page_load() {
    local host=$1
    local url_prefix=$2
    
    #
    # load page
    #
    `time -p wget -q -m -k -E -p -np --no-cache --no-cookies -e robots=off --user-agent="Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0" http://$host/$url_prefix &> time_wget_output_test.txt`

    local real=`cat time_wget_output_test.txt | grep real | sed -e 's/real//g' | sed -e "s/[[:space:]]\+//g"`
    #user=`cat time_wget_output_test.txt | grep user | sed -e 's/user//g' | sed -e "s/[[:space:]]\+//g"`
    #sys=`cat time_wget_output_test.txt | grep sys | sed -e 's/sys//g' | sed -e "s/[[:space:]]\+//g"`

    rm time_wget_output_test.txt

    if [ -d $host ]
    then
        rm -r $host
    fi

    echo "$real"
}

#
# Function for show help
#
function show_help() {
    local app=$1
    
    echo ""
    echo "Help:"
    echo ""
    echo "  --help - show this help (-h)"
    echo ""
    echo "Input args:"
    echo "  1 - host (if empty - used 127.0.0.1:8080)"
    echo "  2 - application URL prefix (may be empty)"
    echo "  3 - number of query iterations (may be empty; default: 10)"
    echo ""
    echo "Examples:"
    echo "  #> sh $app"
    echo ""
    echo "  #> sh $app 127.0.0.1:8080 my-project"
    echo ""
    echo "  #> sh $app 127.0.0.1:8080 my-project 5"
    echo ""
    echo "  #> sh $app 127.0.0.1:8080 \"\" 5"
    echo ""
    echo "  #> sh $app \"\" \"\" 5"
    echo ""
}


#
# Main
# Input args:
# 1 - host (if empty - used 127.0.0.1:8080)
# 2 - application URL prefix (may be empty)
#

#
# check show help
#
for var in "$@" 
do
    if [ "$var" == "--help" ] || [ "$var" == "-h" ]
    then
        show_help "$0"
        exit 0;
    fi
done

app_host=$1
app_url_prefix=$2
check_iterations=$3

if [ -z "$app_host" ]
then
    app_host="127.0.0.1:8080"
fi

if [ -z "$check_iterations" ] || [ "$check_iterations" -le "0" ]
then
    check_iterations=10
fi

#
# check page http code
#
http_code="$(test_page_code "$app_host" "$app_url_prefix")"
if [ "$http_code" -ge "400" ]
then
    echo "ERROR: Invalid response HTTP code (code: $http_code; host: \"http://$app_host/$app_url_prefix\")"
    exit 1;
fi

summ=0
for (( i=1; i<=$check_iterations; i++ ))
do    
    #
    # load page
    #
    result="$(test_page_load "$app_host" "$app_url_prefix")"
    echo " LOAD: $result sec (for iteration $i)"
    
    #
    # calculate summary
    #
    summ=`awk "BEGIN{ print $summ + $result }"`
done

echo "-----------------------------------"
echo "TOTAL: $summ sec (for $check_iterations iterations)"

#
# calculate result
#
mean=`awk "BEGIN{ print $summ / $check_iterations }"`
echo " MEAN: $mean sec"
