#!/bin/bash

# $1 modem number

case "$1" in
	1)
		echo "switch to modem 1"
		route delete default;
		route delete default;
		route add default gw 192.168.1.1
		iptables -F
		iptables -X
		iptables -F -t nat
		iptables -X -t nat
		iptables -t nat -A POSTROUTING -o enx582c80139263 -j SNAT --to-source 192.168.1.100
		iptables -t mangle -A PREROUTING -i enx582c80139263 -j TTL --ttl-set 64
		killall ssh
	;;

	2)
		echo "switch to modem 2";
		route delete default;
		route delete default;
		route add default gw 192.168.2.1
		iptables -F
		iptables -X
		iptables -F -t nat
		iptables -X -t nat		
		iptables -t nat -A POSTROUTING -o enp2s0 -j SNAT --to-source 192.168.2.2
		iptables -t mangle -A PREROUTING -i enp2s0 -j TTL --ttl-set 64
		killall ssh
	;;

	current)
                CURR_IP=`route -n | head -n 3 | tail -n 1 | awk '{print $2}'`
                [ "$CURR_IP" = "192.168.1.1" ] && echo "current modem: 1" && exit 1
                [ "$CURR_IP" = "192.168.2.1" ] && echo "current modem: 2" && exit 2
                echo "current modem: unknown"
                exit -1
        ;;

	*)
		echo "Incorrect modem number"
	;;
esac
