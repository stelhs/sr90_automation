#!/bin/bash

# $1 modem number

case "$1" in
	1)
		echo "switch to modem 1"
		route delete default; route add default gw 192.168.1.1
		iptables -F -t nat
		iptables -t nat -A POSTROUTING -o eth0 -j SNAT --to-source 192.168.1.100
	;;

	2)
		echo "switch to modem 2";
                route delete default; route add default gw 192.168.2.1
                iptables -F -t nat
                iptables -t nat -A POSTROUTING -o p1p1 -j SNAT --to-source 192.168.2.2
	;;
	
	*)
		echo "Incorrect modem number"
	;;
esac
